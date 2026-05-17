<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\DocumentStatusHistory;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use Illuminate\Support\Facades\DB;

class DocumentWorkflowService
{
    public function __construct(
        private readonly NumberingService $numbering,
    ) {}

    /**
     * Create a draft document.
     */
    public function createDraft(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            $document = Document::create([
                'company_id' => $data['company_id'],
                'document_type' => $data['document_type'],
                'status' => Document::STATUS_DRAFT,
                'customer_id' => $data['customer_id'] ?? null,
                'document_date' => $data['document_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'currency' => $data['currency'] ?? 'MYR',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'show_amount_in_words' => $data['show_amount_in_words'] ?? false,
                'amount_in_words_locale' => $data['amount_in_words_locale'] ?? null,
                'amount_in_words_currency' => $data['amount_in_words_currency'] ?? null,
            ]);

            // Create items
            if (! empty($data['items'])) {
                foreach ($data['items'] as $i => $itemData) {
                    $lineTotal = ($itemData['quantity'] ?? 1) * ($itemData['unit_price'] ?? 0)
                        - ($itemData['discount'] ?? 0);
                    DocumentItem::create([
                        'document_id' => $document->id,
                        'product_id' => $itemData['product_id'] ?? null,
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'] ?? 1,
                        'uom' => $itemData['uom'] ?? 'unit',
                        'unit_price' => $itemData['unit_price'] ?? 0,
                        'discount' => $itemData['discount'] ?? 0,
                        'line_total' => $lineTotal,
                        'tax_type' => $itemData['tax_type'] ?? null,
                        'tax_rate' => $itemData['tax_rate'] ?? null,
                        'tax_amount' => $itemData['tax_amount'] ?? 0,
                        'classification_code' => $itemData['classification_code'] ?? null,
                        'tax_exemption_reason' => $itemData['tax_exemption_reason'] ?? null,
                        'sort_order' => $itemData['sort_order'] ?? $i,
                    ]);
                }
            }

            $document->load('items');
            $document->recomputeTotals();
            $document->draft_hash = md5(json_encode($document->items->toArray()));
            $document->save();

            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => null,
                'to_status' => Document::STATUS_DRAFT,
            ]);

            return $document;
        });
    }

    /**
     * Issue a draft document. Allocates official number and snapshots.
     */
    public function issue(int $documentId, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($documentId, $userId) {
            $document = Document::lockForUpdate()->findOrFail($documentId);

            if (! $document->isDraft()) {
                throw new \RuntimeException("Only draft documents can be issued. Current status: {$document->status}");
            }

            // Allocate official number
            $year = $document->document_date
                ? \Carbon\Carbon::parse($document->document_date)->year
                : now()->year;
            $officialNumber = $this->numbering->allocate(
                $document->company_id,
                $document->document_type,
                $year
            );

            // Recompute totals for safety
            $document->load('items');
            $document->recomputeTotals();

            // Take issue-time snapshots
            $company = Company::find($document->company_id);
            $customer = $document->customer_id ? Customer::find($document->customer_id) : null;

            $document->update([
                'status' => Document::STATUS_ISSUED,
                'official_number' => $officialNumber,
                'issued_at' => now(),
                'issue_timezone_snapshot' => 'Asia/Kuala_Lumpur',
                'issuer_snapshot_json' => $company ? $company->only(['name', 'code', 'address', 'phone', 'email', 'registration_number']) : null,
                'buyer_snapshot_json' => $customer ? $customer->only(['name', 'address', 'phone', 'email', 'tax_identifier']) : null,
                'draft_hash' => md5(json_encode($document->items->toArray())),
            ]);

            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => Document::STATUS_DRAFT,
                'to_status' => Document::STATUS_ISSUED,
                'changed_by_user_id' => $userId,
            ]);

            return $document;
        });
    }

    /**
     * Convert a quotation to an invoice or delivery order.
     */
    public function convert(int $sourceId, string $targetType, array $overrides = []): Document
    {
        return DB::transaction(function () use ($sourceId, $targetType, $overrides) {
            $source = Document::findOrFail($sourceId);

            if ($source->status !== Document::STATUS_DRAFT && $source->status !== Document::STATUS_ISSUED) {
                throw new \RuntimeException("Cannot convert from status: {$source->status}");
            }

            $target = Document::create([
                'company_id' => $source->company_id,
                'document_type' => $targetType,
                'status' => Document::STATUS_DRAFT,
                'customer_id' => $source->customer_id,
                'document_date' => $overrides['document_date'] ?? now()->toDateString(),
                'due_date' => $overrides['due_date'] ?? null,
                'currency' => $source->currency,
                'notes' => $overrides['notes'] ?? $source->notes,
                'terms' => $overrides['terms'] ?? $source->terms,
                'converted_from_id' => $source->id,
                'show_amount_in_words' => $source->show_amount_in_words,
            ]);

            // Copy items
            foreach ($source->items as $i => $item) {
                DocumentItem::create([
                    'document_id' => $target->id,
                    'product_id' => $item->product_id,
                    'description' => $overrides['items'][$i]['description'] ?? $item->description,
                    'quantity' => $overrides['items'][$i]['quantity'] ?? $item->quantity,
                    'uom' => $item->uom,
                    'unit_price' => $overrides['items'][$i]['unit_price'] ?? $item->unit_price,
                    'discount' => $overrides['items'][$i]['discount'] ?? $item->discount,
                    'line_total' => 0, // recomputed below
                    'sort_order' => $i,
                ]);
            }

            $target->load('items');
            $target->recomputeTotals();
            $target->save();

            // Mark source as converted if it was issued
            if ($source->isIssued()) {
                $source->update(['status' => Document::STATUS_CONVERTED]);
                DocumentStatusHistory::create([
                    'document_id' => $source->id,
                    'from_status' => Document::STATUS_ISSUED,
                    'to_status' => Document::STATUS_CONVERTED,
                ]);
            }

            DocumentStatusHistory::create([
                'document_id' => $target->id,
                'from_status' => null,
                'to_status' => Document::STATUS_DRAFT,
            ]);

            return $target;
        });
    }

    /**
     * Void a document with reason.
     */
    public function void(int $documentId, string $reason, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($documentId, $reason, $userId) {
            $document = Document::lockForUpdate()->findOrFail($documentId);

            if (! $document->isDraft() && ! $document->isIssued()) {
                throw new \RuntimeException("Cannot void document with status: {$document->status}");
            }

            $previousStatus = $document->status;
            $document->update([
                'status' => Document::STATUS_VOID,
                'voided_at' => now(),
                'void_reason' => $reason,
                'voided_by' => $userId,
            ]);

            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => $previousStatus,
                'to_status' => Document::STATUS_VOID,
                'changed_by_user_id' => $userId,
                'reason' => $reason,
            ]);

            return $document;
        });
    }

    /**
     * Record a payment and optionally allocate to documents.
     */
    public function recordPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'company_id' => $data['company_id'],
                'payment_date' => $data['payment_date'] ?? now()->toDateString(),
                'amount' => $data['amount'],
                'unallocated_amount' => $data['amount'],
                'method' => $data['method'] ?? 'bank_transfer',
                'reference_number' => $data['reference_number'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            // Allocate to documents if provided
            if (! empty($data['allocations'])) {
                foreach ($data['allocations'] as $allocation) {
                    $this->allocatePaymentToDocument(
                        $payment,
                        $allocation['document_id'],
                        $allocation['amount']
                    );
                }
            }

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Allocate a portion of a payment to a document.
     */
    public function allocatePaymentToDocument(Payment $payment, int $documentId, float $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $documentId, $amount) {
            $payment->refresh();

            if ($amount > $payment->unallocated_amount) {
                throw new \RuntimeException(
                    "Allocation amount {$amount} exceeds unallocated {$payment->unallocated_amount}"
                );
            }

            $document = Document::findOrFail($documentId);
            if ($document->company_id !== $payment->company_id) {
                throw new \RuntimeException('Payment and document must belong to the same company');
            }

            $allocation = PaymentAllocation::create([
                'payment_id' => $payment->id,
                'document_id' => $documentId,
                'amount' => $amount,
            ]);

            $payment->decrement('unallocated_amount', $amount);

            return $allocation;
        });
    }

    /**
     * Verify that issued snapshots haven't changed.
     */
    public function verifySnapshotIntegrity(Document $document): bool
    {
        if (! $document->isIssued()) {
            return true;
        }

        $company = Company::find($document->company_id);
        $currentIssuer = $company->only(['name', 'code', 'address', 'phone', 'email', 'registration_number']);

        return $document->issuer_snapshot_json === $currentIssuer;
    }
}
