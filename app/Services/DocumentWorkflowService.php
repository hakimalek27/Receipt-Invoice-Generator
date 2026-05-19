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
    private const CONVERSION_TARGETS = [
        'quotation' => ['invoice', 'delivery_order'],
        'invoice' => ['delivery_order'],
    ];

    private const RECEIVABLE_DOCUMENT_TYPES = [
        'invoice',
        'cash_bill',
        'debit_note',
    ];

    public function __construct(
        private readonly NumberingService $numbering,
        private readonly AmountInWordsService $amountInWords,
        private readonly PdfRenderService $pdfRender,
        private readonly DocumentFingerprintService $fingerprint,
    ) {}

    /**
     * Create a draft document.
     */
    public function createDraft(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            // Cross-company validation
            $companyId = $data['company_id'];
            if (! empty($data['customer_id'])) {
                $customer = Customer::where('id', $data['customer_id'])
                    ->where('company_id', $companyId)->first();
                if (! $customer) {
                    throw new \RuntimeException('Customer does not belong to this company');
                }
            }
            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    if (! empty($itemData['product_id'])) {
                        $product = \App\Models\Product::where('id', $itemData['product_id'])
                            ->where('company_id', $companyId)->first();
                        if (! $product) {
                            throw new \RuntimeException('Product does not belong to this company');
                        }
                    }
                }
            }

            $currency = strtoupper($data['currency'] ?? 'MYR');
            $fxRate = $data['fx_rate'] ?? null;
            if ($currency !== 'MYR' && ((float) $fxRate) <= 0) {
                throw new \RuntimeException('Non-MYR documents require an FX rate snapshot');
            }

            $document = Document::create([
                'company_id' => $data['company_id'],
                'document_type' => $data['document_type'],
                'status' => Document::STATUS_DRAFT,
                'customer_id' => $data['customer_id'] ?? null,
                'document_date' => $data['document_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? null,
                'currency' => $currency,
                'fx_rate' => $fxRate,
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'product_line' => $data['product_line'] ?? null,
                'include_arabic_salutation' => $data['include_arabic_salutation'] ?? false,
                'show_amount_in_words' => $data['show_amount_in_words'] ?? false,
                'amount_in_words_locale' => $data['amount_in_words_locale'] ?? null,
                'amount_in_words_currency' => $data['amount_in_words_currency'] ?? null,
            ]);

            // Create items
            if (! empty($data['items'])) {
                foreach ($data['items'] as $i => $itemData) {
                    DocumentItem::create($this->itemPayload($document->id, $itemData, $i));
                }
            }

            $document->load('items');
            $document->recomputeTotals();
            $document->save();
            $document->draft_hash = $this->fingerprint->hash($document);
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
    public function issue(
        int $documentId,
        ?int $userId = null,
        ?string $expectedDraftHash = null,
        ?float $confirmedTotal = null
    ): Document
    {
        return DB::transaction(function () use ($documentId, $userId, $expectedDraftHash, $confirmedTotal) {
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
            $document->save();
            $currentDraftHash = $this->fingerprint->hash($document);

            if ($expectedDraftHash !== null && ! hash_equals($currentDraftHash, $expectedDraftHash)) {
                throw new \RuntimeException('Draft has been modified');
            }

            if ($confirmedTotal !== null && round((float) $confirmedTotal, 2) !== round((float) $document->grand_total, 2)) {
                throw new \RuntimeException('Total mismatch');
            }

            // Take issue-time snapshots
            $company = Company::find($document->company_id);
            $customer = $document->customer_id ? Customer::find($document->customer_id) : null;

            // Generate amount-in-words if enabled
            $amountWordsText = null;
            if ($document->show_amount_in_words) {
                $amountWordsText = $this->amountInWords->convert(
                    (float) $document->grand_total,
                    $document->amount_in_words_locale ?? 'ms_MY',
                    $document->amount_in_words_currency ?? 'MYR'
                );
            }

            $document->update([
                'status' => Document::STATUS_ISSUED,
                'official_number' => $officialNumber,
                'issued_at' => now(),
                'issue_timezone_snapshot' => 'Asia/Kuala_Lumpur',
                'issuer_snapshot_json' => $company ? $this->companySnapshot($company) : null,
                'buyer_snapshot_json' => $customer ? $this->customerSnapshot($customer) : null,
                'bank_snapshot_json' => $company ? $this->companyBankSnapshot($company) : null,
                'terms_snapshot_json' => ['terms' => $document->terms],
                'tax_snapshot_json' => ['tax_total' => $document->tax_total],
                'currency_fx_snapshot_json' => ['currency' => $document->currency, 'fx_rate' => $document->fx_rate],
                'amount_in_words_text' => $amountWordsText,
                'draft_hash' => $currentDraftHash,
            ]);

            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => Document::STATUS_DRAFT,
                'to_status' => Document::STATUS_ISSUED,
                'changed_by_user_id' => $userId,
            ]);

            // Render immutable PDF
            $this->pdfRender->render($document);

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
            if (! in_array($targetType, self::CONVERSION_TARGETS[$source->document_type] ?? [], true)) {
                throw new \RuntimeException("Cannot convert {$source->document_type} to {$targetType}");
            }

            $target = Document::create([
                'company_id' => $source->company_id,
                'document_type' => $targetType,
                'status' => Document::STATUS_DRAFT,
                'customer_id' => $source->customer_id,
                'document_date' => $overrides['document_date'] ?? now()->toDateString(),
                'due_date' => $overrides['due_date'] ?? null,
                'currency' => $source->currency,
                'fx_rate' => $source->fx_rate,
                'notes' => $overrides['notes'] ?? $source->notes,
                'terms' => $overrides['terms'] ?? $source->terms,
                'converted_from_id' => $source->id,
                'show_amount_in_words' => $source->show_amount_in_words,
                'amount_in_words_locale' => $source->amount_in_words_locale,
                'amount_in_words_currency' => $source->amount_in_words_currency,
            ]);

            // Copy items
            foreach ($source->items as $i => $item) {
                $override = $overrides['items'][$i] ?? [];
                DocumentItem::create($this->itemPayload($target->id, [
                    'product_id' => $item->product_id,
                    'description' => $override['description'] ?? $item->description,
                    'section_header' => $override['section_header'] ?? $item->section_header,
                    'image_url' => $override['image_url'] ?? $item->image_url,
                    'quantity' => $override['quantity'] ?? $item->quantity,
                    'uom' => $override['uom'] ?? $item->uom,
                    'unit_price' => $override['unit_price'] ?? $item->unit_price,
                    'cost_unit' => $override['cost_unit'] ?? $item->cost_unit,
                    'discount' => $override['discount'] ?? $item->discount,
                    'tax_type' => $override['tax_type'] ?? $item->tax_type,
                    'tax_rate' => $override['tax_rate'] ?? $item->tax_rate,
                    'tax_amount' => $override['tax_amount'] ?? $item->tax_amount,
                    'classification_code' => $override['classification_code'] ?? $item->classification_code,
                    'tax_exemption_reason' => $override['tax_exemption_reason'] ?? $item->tax_exemption_reason,
                    'sort_order' => $i,
                ], $i));
            }

            $target->load('items');
            $target->recomputeTotals();
            $target->save();
            $target->draft_hash = $this->fingerprint->hash($target);
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
     * Clone an existing document into a fresh independent draft.
     * Preserves customer + items + flags; resets dates/status/number/snapshots;
     * does NOT link as a conversion (converted_from_id stays null).
     */
    public function duplicate(int $sourceId, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($sourceId, $userId) {
            $source = Document::with('items')->findOrFail($sourceId);

            $draft = Document::create([
                'company_id' => $source->company_id,
                'document_type' => $source->document_type,
                'status' => Document::STATUS_DRAFT,
                'customer_id' => $source->customer_id,
                'document_date' => now()->toDateString(),
                'due_date' => null,
                'currency' => $source->currency,
                'fx_rate' => $source->fx_rate,
                'notes' => $source->notes,
                'terms' => $source->terms,
                'product_line' => $source->product_line,
                'include_arabic_salutation' => (bool) $source->include_arabic_salutation,
                'show_amount_in_words' => (bool) $source->show_amount_in_words,
                'amount_in_words_locale' => $source->amount_in_words_locale,
                'amount_in_words_currency' => $source->amount_in_words_currency,
                'converted_from_id' => null,
            ]);

            foreach ($source->items as $i => $item) {
                DocumentItem::create($this->itemPayload($draft->id, [
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'section_header' => $item->section_header,
                    'image_url' => $item->image_url,
                    'quantity' => $item->quantity,
                    'uom' => $item->uom,
                    'unit_price' => $item->unit_price,
                    'cost_unit' => $item->cost_unit,
                    'discount' => $item->discount,
                    'tax_type' => $item->tax_type,
                    'tax_rate' => $item->tax_rate,
                    'tax_amount' => $item->tax_amount,
                    'classification_code' => $item->classification_code,
                    'tax_exemption_reason' => $item->tax_exemption_reason,
                    'sort_order' => $i,
                ], $i));
            }

            $draft->load('items');
            $draft->recomputeTotals();
            $draft->save();
            $draft->draft_hash = $this->fingerprint->hash($draft);
            $draft->save();

            DocumentStatusHistory::create([
                'document_id' => $draft->id,
                'from_status' => null,
                'to_status' => Document::STATUS_DRAFT,
                'changed_by_user_id' => $userId,
                'reason' => 'Duplicated from #'.$source->id,
            ]);

            return $draft;
        });
    }

    /**
     * Void a document with reason.
     */
    public function void(int $documentId, string $reason, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($documentId, $reason, $userId) {
            $document = Document::lockForUpdate()->findOrFail($documentId);

            if (trim($reason) === '') {
                throw new \RuntimeException('Void reason is required');
            }

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
                'currency' => $data['currency'] ?? 'MYR',
                'fx_rate' => $data['fx_rate'] ?? null,
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

            if (! empty($data['create_official_receipt'])) {
                $this->createOfficialReceiptForPayment($payment, $data['user_id'] ?? null);
            }

            return $payment->fresh(['allocations', 'receiptDocument']);
        });
    }

    /**
     * Allocate a portion of a payment to a document.
     */
    public function allocatePaymentToDocument(Payment $payment, int $documentId, float $amount): PaymentAllocation
    {
        return DB::transaction(function () use ($payment, $documentId, $amount) {
            $payment = Payment::lockForUpdate()->findOrFail($payment->id);

            if ($amount <= 0) {
                throw new \RuntimeException('Allocation amount must be positive');
            }
            if ($amount > $payment->unallocated_amount) {
                throw new \RuntimeException(
                    "Allocation amount {$amount} exceeds unallocated {$payment->unallocated_amount}"
                );
            }

            $document = Document::lockForUpdate()->findOrFail($documentId);
            if ($document->company_id !== $payment->company_id) {
                throw new \RuntimeException('Payment and document must belong to the same company');
            }
            if (! $document->isIssued()) {
                throw new \RuntimeException('Payments can only be allocated to issued documents');
            }
            if (! in_array($document->document_type, self::RECEIVABLE_DOCUMENT_TYPES, true)) {
                throw new \RuntimeException("Payments cannot be allocated to {$document->document_type}");
            }
            if ($payment->currency !== $document->currency) {
                throw new \RuntimeException('Payment and document currency must match');
            }

            $alreadyAllocated = (float) $document->paymentAllocations()->sum('amount');
            $outstanding = round((float) $document->grand_total - $alreadyAllocated, 2);
            if ($amount > $outstanding) {
                throw new \RuntimeException(
                    "Allocation amount {$amount} exceeds outstanding {$outstanding}"
                );
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
        $currentIssuer = $company ? $this->companySnapshot($company) : null;

        return $document->issuer_snapshot_json === $currentIssuer;
    }

    private function companySnapshot(Company $company): array
    {
        return [
            'name' => $company->name,
            'code' => $company->code,
            'address' => $company->address,
            'address_line_2' => $company->address_line_2,
            'city' => $company->city,
            'state' => $company->state,
            'postcode' => $company->postcode,
            'country' => $company->country,
            'canonical_address' => $this->canonicalAddress([
                $company->address,
                $company->address_line_2,
                $company->postcode,
                $company->city,
                $company->state,
                $company->country,
            ]),
            'phone' => $company->phone,
            'email' => $company->email,
            'registration_number' => $company->registration_number,
            'tin' => $company->tin,
            'sst_registration_number' => $company->sst_registration_number,
            'msic_code' => $company->msic_code,
            'business_activity_description' => $company->business_activity_description,
            'logo_path' => $company->logo_path,
            'stamp_path' => $company->stamp_path,
            'signature_path' => $company->signature_path,
            'brand_primary' => $company->brand_primary,
            'brand_secondary' => $company->brand_secondary,
            'brand_accent' => $company->brand_accent,
            'pdf_boilerplate' => $company->pdf_boilerplate,
        ];
    }

    private function customerSnapshot(Customer $customer): array
    {
        return [
            'name' => $customer->name,
            'address' => $customer->address,
            'address_line_2' => $customer->address_line_2,
            'city' => $customer->city,
            'state' => $customer->state,
            'postcode' => $customer->postcode,
            'country' => $customer->country,
            'canonical_address' => $this->canonicalAddress([
                $customer->address,
                $customer->address_line_2,
                $customer->postcode,
                $customer->city,
                $customer->state,
                $customer->country,
            ]),
            'phone' => $customer->phone,
            'email' => $customer->email,
            'tax_identifier' => $customer->tax_identifier,
            'brn_registration_number' => $customer->brn_registration_number,
            'sst_registration_number' => $customer->sst_registration_number,
            'msic_code' => $customer->msic_code,
        ];
    }

    private function companyBankSnapshot(Company $company): array
    {
        return $company->bankAccounts()
            ->where('is_active', true)
            ->get()
            ->map(fn ($bank) => [
                'bank_name' => $bank->bank_name,
                'account_number' => $bank->account_number,
                'account_holder' => $bank->account_holder,
                'swift_code' => $bank->swift_code,
                'is_primary' => (bool) $bank->is_primary,
            ])
            ->all();
    }

    private function canonicalAddress(array $parts): string
    {
        return collect($parts)
            ->filter(fn ($part) => trim((string) $part) !== '')
            ->map(fn ($part) => preg_replace('/\s+/', ' ', trim((string) $part)))
            ->implode(', ');
    }

    public function createOfficialReceiptForPayment(Payment $payment, ?int $userId = null): Document
    {
        return DB::transaction(function () use ($payment, $userId) {
            $payment = Payment::with('allocations.document')
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->receipt_document_id) {
                return Document::findOrFail($payment->receipt_document_id);
            }

            $firstDocument = $payment->allocations->first()?->document;

            if ($payment->allocations->isNotEmpty()) {
                $items = $payment->allocations->map(function ($allocation) use ($payment) {
                    $sourceNumber = $allocation->document?->official_number ?? "DOC-{$allocation->document_id}";
                    $description = "Payment for {$sourceNumber}";
                    if ($payment->reference_number) {
                        $description .= " (Ref: {$payment->reference_number})";
                    }

                    return [
                        'description' => $description,
                        'quantity' => 1,
                        'unit_price' => (float) $allocation->amount,
                    ];
                })->all();
            } else {
                $description = 'Payment received';
                if ($payment->reference_number) {
                    $description .= " ({$payment->reference_number})";
                }
                $items = [[
                    'description' => $description,
                    'quantity' => 1,
                    'unit_price' => (float) $payment->amount,
                ]];
            }

            $receipt = $this->createDraft([
                'company_id' => $payment->company_id,
                'document_type' => 'official_receipt',
                'customer_id' => $firstDocument?->customer_id,
                'document_date' => $payment->payment_date?->toDateString() ?? now()->toDateString(),
                'currency' => $payment->currency,
                'fx_rate' => $payment->fx_rate,
                'notes' => $payment->notes,
                'show_amount_in_words' => true,
                'amount_in_words_locale' => 'en_WEHDAH',
                'amount_in_words_currency' => $payment->currency,
                'items' => $items,
            ]);

            $issuedReceipt = $this->issue(
                $receipt->id,
                $userId,
                $receipt->draft_hash,
                (float) $receipt->grand_total
            );

            $payment->update(['receipt_document_id' => $issuedReceipt->id]);

            return $issuedReceipt;
        });
    }

    private function itemPayload(int $documentId, array $itemData, int $index): array
    {
        $quantity = (float) ($itemData['quantity'] ?? 1);
        $unitPrice = (float) ($itemData['unit_price'] ?? 0);
        $discount = (float) ($itemData['discount'] ?? 0);
        $lineTotal = round(max(0, ($quantity * $unitPrice) - $discount), 2);

        return [
            'document_id' => $documentId,
            'product_id' => $itemData['product_id'] ?? null,
            'description' => $itemData['description'],
            'section_header' => $itemData['section_header'] ?? null,
            'image_url' => $itemData['image_url'] ?? null,
            'quantity' => $quantity,
            'uom' => $itemData['uom'] ?? 'unit',
            'unit_price' => $unitPrice,
            'cost_unit' => isset($itemData['cost_unit']) ? (float) $itemData['cost_unit'] : null,
            'discount' => $discount,
            'line_total' => $lineTotal,
            'tax_type' => $itemData['tax_type'] ?? null,
            'tax_rate' => $itemData['tax_rate'] ?? null,
            'tax_amount' => $itemData['tax_amount'] ?? 0,
            'classification_code' => $itemData['classification_code'] ?? null,
            'tax_exemption_reason' => $itemData['tax_exemption_reason'] ?? null,
            'sort_order' => $itemData['sort_order'] ?? $index,
        ];
    }
}
