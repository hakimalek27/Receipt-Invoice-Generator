<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use App\Models\DocumentItem;
use App\Models\DocumentStatusHistory;
use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Services\DocumentFingerprintService;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly DocumentFingerprintService $fingerprint,
    ) {}

    /**
     * Issue a document with idempotency protection.
     */
    public function issue(Request $request, int $id): JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header required'], 400);
        }

        $document = Document::findOrFail($id);

        // Verify company scope
        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request)
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        // Require draft_hash
        $draftHash = $request->input('draft_hash');
        if (! $draftHash) {
            return response()->json(['error' => 'draft_hash required'], 400);
        }

        $confirmedTotal = $request->input('confirmed_total');
        if ($confirmedTotal === null) {
            return response()->json(['error' => 'confirmed_total required'], 400);
        }

        $requestHash = hash('sha256', json_encode([
            'action' => 'document_issue',
            'document_id' => $document->id,
            'draft_hash' => $draftHash,
            'confirmed_total' => number_format((float) $confirmedTotal, 2, '.', ''),
        ], JSON_THROW_ON_ERROR));

        $scope = [
            'company_id' => $document->company_id,
            'user_id' => $request->user()->id,
            'document_id' => $document->id,
            'resource_type' => 'document_issue',
            'key' => $idempotencyKey,
        ];

        $existing = IdempotencyKey::where($scope)->first();
        if ($existing) {
            if ($existing->expires_at && $existing->expires_at->isPast()) {
                $existing->delete();
            } elseif (! hash_equals((string) $existing->request_hash, $requestHash)) {
                return response()->json(['error' => 'Idempotency-Key reused with different request'], 409);
            } elseif ($existing->status === 'succeeded' && $existing->resource_id) {
                return response()->json($existing->response_data, 200);
            } else {
                return response()->json(['error' => 'Request in progress'], 409);
            }
        }

        $idempotency = IdempotencyKey::create($scope + [
            'draft_hash' => $draftHash,
            'request_hash' => $requestHash,
            'status' => 'processing',
            'expires_at' => now()->addHours(24),
        ]);

        try {
            $issued = $this->workflow->issue(
                $id,
                $request->user()->id,
                $draftHash,
                (float) $confirmedTotal
            );

            $responseData = [
                'id' => $issued->id,
                'status' => $issued->status,
                'official_number' => $issued->official_number,
                'issued_at' => $issued->issued_at->toIso8601String(),
                'grand_total' => $issued->grand_total,
            ];

            $idempotency->update([
                'resource_id' => $issued->id,
                'response_data' => $responseData,
                'status' => 'succeeded',
            ]);

            return response()->json($responseData, 200);
        } catch (\RuntimeException $e) {
            $idempotency->delete();

            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Create a draft document.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_attention_to' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'product_line' => 'nullable|string|in:scentury,standard',
            'include_arabic_salutation' => 'nullable|boolean',
            'show_computer_generated_footer' => 'nullable|boolean',
            'show_amount_in_words' => 'nullable|boolean',
            'amount_in_words_locale' => 'nullable|string',
            'amount_in_words_currency' => 'nullable|string|size:3',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer',
            'items.*.description' => 'required|string',
            'items.*.section_header' => 'nullable|string|max:255',
            'items.*.image_url' => ['nullable', 'string', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.cost_unit' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.classification_code' => 'nullable|string',
            'items.*.tax_exemption_reason' => 'nullable|string',
        ]);

        $data['company_id'] = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        $data['currency'] = strtoupper($data['currency'] ?? 'MYR');
        if ($data['currency'] !== 'MYR' && empty($data['fx_rate'])) {
            return response()->json(['error' => 'Non-MYR documents require an FX rate snapshot'], 422);
        }

        $data = $this->resolveCustomerName($data, $data['company_id']);

        try {
            $draft = $this->workflow->createDraft($data);
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($draft->load('items'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::with('items')->findOrFail($id);
        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request)
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }
        // Draft and issued documents are editable so admins can correct
        // typos / numbers after the fact. Void / cancelled (explicit
        // lifecycle dead-ends) stay locked. The legacy 'converted' status
        // is gone (PR moves to derive model where source stays issued).
        if (! in_array($document->status, [Document::STATUS_DRAFT, Document::STATUS_ISSUED], true)) {
            return response()->json(['error' => 'Documents in '.$document->status.' status cannot be edited'], 422);
        }

        $data = $request->validate([
            'document_type' => 'nullable|string|max:50',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:255',
            'customer_attention_to' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'product_line' => 'nullable|string|in:scentury,standard',
            'include_arabic_salutation' => 'nullable|boolean',
            'show_computer_generated_footer' => 'nullable|boolean',
            'show_amount_in_words' => 'nullable|boolean',
            'amount_in_words_locale' => 'nullable|string',
            'amount_in_words_currency' => 'nullable|string|size:3',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer',
            'items.*.description' => 'required_with:items|string',
            'items.*.section_header' => 'nullable|string|max:255',
            'items.*.image_url' => ['nullable', 'string', 'regex:/^data:image\/(png|jpe?g|webp);base64,/'],
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.uom' => 'nullable|string|max:20',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.cost_unit' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.classification_code' => 'nullable|string',
            'items.*.tax_exemption_reason' => 'nullable|string',
        ]);

        $data['currency'] = strtoupper($data['currency'] ?? $document->currency ?? 'MYR');
        if ($data['currency'] !== 'MYR' && empty($data['fx_rate']) && empty($document->fx_rate)) {
            return response()->json(['error' => 'Non-MYR documents require an FX rate snapshot'], 422);
        }

        $data = $this->resolveCustomerName($data, $document->company_id);

        if (! empty($data['customer_id'])
            && ! Customer::whereKey($data['customer_id'])->where('company_id', $document->company_id)->exists()) {
            return response()->json(['error' => 'Customer does not belong to this company'], 422);
        }
        foreach (($data['items'] ?? []) as $item) {
            if (! empty($item['product_id'])
                && ! Product::whereKey($item['product_id'])->where('company_id', $document->company_id)->exists()) {
                return response()->json(['error' => 'Product does not belong to this company'], 422);
            }
        }

        try {
            DB::transaction(function () use ($document, $data) {
                $wasFrozen = $document->isIssued();

                $document->update(collect($data)->except('items')->all());

                if (array_key_exists('items', $data)) {
                    $document->items()->delete();
                    foreach ($data['items'] ?? [] as $index => $itemData) {
                        DocumentItem::create($this->itemPayload($document->id, $itemData, $index));
                    }
                }

                $document->load('items');
                $document->recomputeTotals();

                if ($wasFrozen) {
                    // The frozen-at-issue snapshots would otherwise hide this
                    // edit from the PDF. Null them so the renderer falls back
                    // to live company/customer/etc. Then mark existing cached
                    // PDFs as not-current so the next preview regenerates.
                    $document->issuer_snapshot_json = null;
                    $document->buyer_snapshot_json = null;
                    $document->bank_snapshot_json = null;
                    $document->terms_snapshot_json = null;
                    $document->tax_snapshot_json = null;
                    $document->currency_fx_snapshot_json = null;
                    $document->pdfRenders()->update(['is_current' => false]);
                }

                $document->save();
                $document->draft_hash = $this->fingerprint->hash($document);
                $document->save();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json($document->fresh(['items', 'customer', 'attachments', 'pdfRenders']));
    }

    public function void(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request)
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        $data = $request->validate(['reason' => 'required|string|min:1']);

        try {
            return response()->json($this->workflow->void($id, $data['reason'], $request->user()->id));
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $document = Document::with('convertedTo:id,document_type,official_number,converted_from_id')
            ->findOrFail($id);
        $companyId = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        if ($document->company_id !== $companyId && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        // Block deletion when this doc is the source of derived children;
        // the user must delete the children first so we don't orphan them.
        if ($document->convertedTo->isNotEmpty()) {
            $kids = $document->convertedTo
                ->map(fn ($c) => ($c->official_number ?? "Draft #{$c->id}").' ('.$c->document_type.')')
                ->implode(', ');

            return response()->json([
                'error' => "Cannot delete — this document has derived children: {$kids}. Delete those first.",
                'children_count' => $document->convertedTo->count(),
            ], 422);
        }

        $previousStatus = $document->status;
        $recycledNumber = $document->official_number;

        DB::transaction(function () use ($document, $previousStatus, $request) {
            DocumentStatusHistory::create([
                'document_id' => $document->id,
                'from_status' => $previousStatus,
                'to_status' => 'deleted',
                'changed_by_user_id' => $request->user()?->id,
                'reason' => $request->input('reason'),
            ]);
            // Soft delete preserves the row for audit; the SoftDeletes
            // default scope hides it, so NumberingService::allocate() will
            // see the (formerly used) sequence number as free again.
            $document->delete();
        });

        return response()->json([
            'deleted' => true,
            'recycled_number' => $recycledNumber,
            'previous_status' => $previousStatus,
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array|min:1|max:100',
            'ids.*' => 'integer',
        ]);

        $companyId = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        $query = Document::with('convertedTo:id,converted_from_id')
            ->whereIn('id', $data['ids']);

        if (! $request->user()->isSuperAdmin()) {
            $query->where('company_id', $companyId);
        }

        $docs = $query->get();
        $deletedIds = [];
        $blocked = [];

        DB::transaction(function () use ($docs, $request, &$deletedIds, &$blocked) {
            foreach ($docs as $doc) {
                if ($doc->convertedTo->isNotEmpty()) {
                    $blocked[] = [
                        'id' => $doc->id,
                        'official_number' => $doc->official_number,
                        'reason' => "Has {$doc->convertedTo->count()} derived child(ren) — delete those first.",
                    ];
                    continue;
                }

                DocumentStatusHistory::create([
                    'document_id' => $doc->id,
                    'from_status' => $doc->status,
                    'to_status' => 'deleted',
                    'changed_by_user_id' => $request->user()?->id,
                ]);
                $doc->delete();
                $deletedIds[] = $doc->id;
            }
        });

        return response()->json([
            'deleted_count' => count($deletedIds),
            'deleted_ids' => $deletedIds,
            'blocked' => $blocked,
        ]);
    }

    /**
     * @deprecated Use bulkDelete() instead. Route kept for backward compat
     * with the existing /api/documents/bulk-delete-drafts URL.
     */
    public function bulkDeleteDrafts(Request $request): JsonResponse
    {
        return $this->bulkDelete($request);
    }

    public function duplicate(Request $request, int $id): JsonResponse
    {
        $source = Document::findOrFail($id);
        $companyId = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        if ($source->company_id !== $companyId && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        $draft = $this->workflow->duplicate($id, $request->user()->id);

        return response()->json($draft->load('items'), 201);
    }

    public function convert(Request $request, int $id): JsonResponse
    {
        $document = Document::findOrFail($id);
        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request)
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        $data = $request->validate([
            'target_type' => 'required|string|in:proforma_invoice,invoice,delivery_order,official_receipt,credit_note,debit_note',
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        try {
            return response()->json(
                $this->workflow->derive($id, $data['target_type'], $data)->load('items'),
                201
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * List documents for the authenticated user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        $query = Document::with('items', 'customer')
            ->forCompany($companyId)
            ->latest();

        if ($type = $request->query('type')) {
            $query->ofType($type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('official_number', 'like', "%{$search}%")
                    ->orWhere('document_type', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"));
            });
        }
        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('document_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('document_date', '<=', $dateTo);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Get a single document.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $document = Document::with('items', 'customer', 'attachments', 'pdfRenders', 'paymentAllocations')
            ->findOrFail($id);

        if ($document->company_id !== \App\Services\ActiveCompanyResolver::resolve($request->user(), $request)
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        return response()->json($document);
    }

    /**
     * Resolve the customer block from the request payload:
     *  - If `customer_id` is set, optionally sync the 5 detail fields back to
     *    the existing Customer (Master Data ↔ document-create 2-way sync).
     *  - Otherwise, if `customer_name` is provided, find-or-create a Customer
     *    scoped to the active company, with the detail fields applied.
     *
     * The customer_* keys are stripped from $data so they never reach the
     * documents table (which has no such columns).
     */
    private function resolveCustomerName(array $data, int $companyId): array
    {
        $name = isset($data['customer_name']) ? trim((string) $data['customer_name']) : '';

        $details = [];
        foreach ([
            'customer_attention_to' => 'attention_to',
            'customer_phone' => 'phone',
            'customer_email' => 'email',
            'customer_address' => 'address',
        ] as $payloadKey => $customerColumn) {
            if (array_key_exists($payloadKey, $data)) {
                $value = $data[$payloadKey];
                if ($value !== null && $value !== '') {
                    $details[$customerColumn] = is_string($value) ? trim($value) : $value;
                } else {
                    $details[$customerColumn] = null;
                }
            }
            unset($data[$payloadKey]);
        }
        unset($data['customer_name']);

        if (! empty($data['customer_id'])) {
            // Existing customer — sync any provided detail fields back to Master Data.
            if (! empty($details)) {
                Customer::where('id', $data['customer_id'])
                    ->where('company_id', $companyId)
                    ->update($details);
            }

            return $data;
        }

        if ($name === '') {
            return $data;
        }

        // New customer typed — create with name + all provided detail fields.
        $customer = Customer::firstOrCreate(
            ['company_id' => $companyId, 'name' => $name],
            array_merge(['is_active' => true, 'country' => 'MY'], $details)
        );
        // If firstOrCreate found an existing record by (company_id, name), apply
        // the detail fields so a returning user can refresh details on the fly.
        if (! empty($details) && $customer->wasRecentlyCreated === false) {
            $customer->update($details);
        }
        $data['customer_id'] = $customer->id;

        return $data;
    }

    private function itemPayload(int $documentId, array $itemData, int $index): array
    {
        $quantity = (float) ($itemData['quantity'] ?? 1);
        $unitPrice = (float) ($itemData['unit_price'] ?? 0);
        $discount = (float) ($itemData['discount'] ?? 0);

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
            'line_total' => round(max(0, ($quantity * $unitPrice) - $discount), 2),
            'tax_type' => $itemData['tax_type'] ?? null,
            'tax_rate' => $itemData['tax_rate'] ?? null,
            'tax_amount' => $itemData['tax_amount'] ?? 0,
            'classification_code' => $itemData['classification_code'] ?? null,
            'tax_exemption_reason' => $itemData['tax_exemption_reason'] ?? null,
            'sort_order' => $itemData['sort_order'] ?? $index,
        ];
    }
}
