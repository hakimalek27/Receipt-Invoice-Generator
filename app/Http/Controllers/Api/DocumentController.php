<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\IdempotencyKey;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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

        // Check existing idempotency record
        $existing = IdempotencyKey::where('key', $idempotencyKey)->first();
        if ($existing) {
            if ($existing->resource_id) {
                return response()->json($existing->response_data, 200);
            }
            return response()->json(['error' => 'Request in progress'], 409);
        }

        // Create idempotency record
        IdempotencyKey::create([
            'key' => $idempotencyKey,
            'resource_type' => 'document_issue',
            'expires_at' => now()->addHours(24),
        ]);

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
        if ($document->draft_hash !== $draftHash) {
            return response()->json(['error' => 'Draft has been modified'], 409);
        }

        // Server recomputes totals — this is done inside issue()
        // The client can send confirmed_total which we compare
        $confirmedTotal = $request->input('confirmed_total');
        if ($confirmedTotal !== null && (float) $confirmedTotal !== (float) $document->grand_total) {
            return response()->json(['error' => 'Total mismatch'], 422);
        }

        try {
            $issued = $this->workflow->issue($id, $request->user()->id);

            $responseData = [
                'id' => $issued->id,
                'status' => $issued->status,
                'official_number' => $issued->official_number,
                'issued_at' => $issued->issued_at->toIso8601String(),
                'grand_total' => $issued->grand_total,
            ];

            // Update idempotency record with result
            IdempotencyKey::where('key', $idempotencyKey)->update([
                'resource_id' => $issued->id,
                'response_data' => $responseData,
            ]);

            return response()->json($responseData, 200);
        } catch (\RuntimeException $e) {
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
            'notes' => 'nullable|string',
            'terms' => 'nullable|string',
            'show_amount_in_words' => 'nullable|boolean',
            'amount_in_words_locale' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.description' => 'required|string',
            'items.*.quantity' => 'nullable|numeric|min:0',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $data['company_id'] = $request->user()->company_id;

        $draft = $this->workflow->createDraft($data);

        return response()->json($draft->load('items'), 201);
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
