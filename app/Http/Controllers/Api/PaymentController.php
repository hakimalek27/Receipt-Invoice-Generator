<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Payment::with('allocations.document', 'receiptDocument')
                ->forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))
                ->latest()
                ->paginate(50)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'payment_date' => 'nullable|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'fx_rate' => 'nullable|numeric|min:0',
            'method' => 'nullable|string|max:30',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'create_official_receipt' => 'nullable|boolean',
            'allocations' => 'nullable|array',
            'allocations.*.document_id' => 'required|integer',
            'allocations.*.amount' => 'required|numeric|min:0.01',
        ]);

        $data['company_id'] = \App\Services\ActiveCompanyResolver::resolve($request->user(), $request);
        $data['user_id'] = $request->user()->id;

        return response()->json($this->workflow->recordPayment($data), 201);
    }

    public function show(Request $request, int $payment): JsonResponse
    {
        $payment = Payment::with('allocations.document', 'receiptDocument')
            ->forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))
            ->findOrFail($payment);

        return response()->json($payment);
    }

    public function generateReceipt(Request $request, int $payment): JsonResponse
    {
        $paymentModel = Payment::forCompany(\App\Services\ActiveCompanyResolver::resolve($request->user(), $request))
            ->findOrFail($payment);

        if ($paymentModel->receipt_document_id) {
            return response()->json([
                'error' => 'Receipt already generated',
                'receipt_document_id' => $paymentModel->receipt_document_id,
            ], 409);
        }

        $this->workflow->createOfficialReceiptForPayment($paymentModel, $request->user()->id);

        return response()->json(
            $paymentModel->fresh(['allocations.document', 'receiptDocument']),
            201
        );
    }
}
