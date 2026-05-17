<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\IdempotencyKey;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
    ) {}

    /**
     * Issue a document with idempotency protection.
     */
    public function issue(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $idempotencyKey = $request->header('Idempotency-Key');
        if (! $idempotencyKey) {
            return response()->json(['error' => 'Idempotency-Key header required'], 400);
        }

        $document = Document::findOrFail($id);

        // Verify company scope
        if ($document->company_id !== $request->user()->company_id
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
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'document_type' => 'required|string',
            'customer_id' => 'nullable|exists:customers,id',
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'show_amount_in_words' => 'nullable|boolean',
            'amount_in_words_locale' => 'nullable|string',
            'amount_in_words_currency' => 'nullable|string|size:3',
            'items' => 'nullable|array',
            'items.*.product_id' => 'nullable|integer',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax_type' => 'nullable|string',
            'items.*.tax_rate' => 'nullable|numeric|min:0',
            'items.*.tax_amount' => 'nullable|numeric|min:0',
            'items.*.classification_code' => 'nullable|string',
            'items.*.tax_exemption_reason' => 'nullable|string',
        ]);

        $data['company_id'] = $request->user()->company_id;

        $draft = $this->workflow->createDraft($data);

        return response()->json($draft->load('items'), 201);
    }

    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $document = Document::with('items')->findOrFail($id);
        if ($document->company_id !== $request->user()->company_id
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }
        if (! $document->isDraft()) {
            return response()->json(['error' => 'Only draft documents can be updated'], 422);
        }

        $data = $request->validate([
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'internal_notes' => 'nullable|string',
        ]);

        $document->update($data);

        return response()->json($document->fresh('items'));
    }

    public function void(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $document = Document::findOrFail($id);
        if ($document->company_id !== $request->user()->company_id
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

    public function convert(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $document = Document::findOrFail($id);
        if ($document->company_id !== $request->user()->company_id
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        $data = $request->validate([
            'target_type' => 'required|string',
            'document_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'items' => 'nullable|array',
        ]);

        try {
            return response()->json(
                $this->workflow->convert($id, $data['target_type'], $data)->load('items'),
                201
            );
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * List documents for the authenticated user's company.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $query = Document::with('items', 'customer')
            ->forCompany($user->company_id)
            ->latest();

        if ($type = $request->query('type')) {
            $query->ofType($type);
        }

        return response()->json($query->paginate(20));
    }

    /**
     * Get a single document.
     */
    public function show(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        $document = Document::with('items', 'customer', 'paymentAllocations')
            ->findOrFail($id);

        if ($document->company_id !== $request->user()->company_id
            && ! $request->user()->isSuperAdmin()) {
            return response()->json(['error' => 'Company scope violation'], 403);
        }

        return response()->json($document);
    }
}
