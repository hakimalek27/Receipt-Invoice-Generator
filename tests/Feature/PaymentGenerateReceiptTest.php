<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use App\Services\DocumentWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentGenerateReceiptTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private DocumentWorkflowService $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['code' => 'WS']);
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        $this->workflow = app(DocumentWorkflowService::class);

        foreach (['invoice' => 'INV', 'official_receipt' => 'REC'] as $type => $code) {
            NumberingPolicy::create([
                'company_id' => $this->company->id,
                'document_type' => $type,
                'prefix' => 'WS-'.$code,
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]);
        }
    }

    public function test_generate_receipt_creates_issued_official_receipt(): void
    {
        Sanctum::actingAs($this->user);
        $payment = $this->createUnreceiptedPayment(150);

        $response = $this->postJson("/api/payments/{$payment->id}/generate-receipt");

        $response->assertCreated()
            ->assertJsonPath('id', $payment->id)
            ->assertJsonPath('receipt_document.document_type', 'official_receipt')
            ->assertJsonPath('receipt_document.status', 'issued');

        $payment->refresh();
        $this->assertNotNull($payment->receipt_document_id);
    }

    public function test_generate_receipt_returns_409_when_already_exists(): void
    {
        Sanctum::actingAs($this->user);
        $payment = $this->createUnreceiptedPayment(75);

        $this->postJson("/api/payments/{$payment->id}/generate-receipt")->assertCreated();
        $existingReceiptId = $payment->fresh()->receipt_document_id;

        $this->postJson("/api/payments/{$payment->id}/generate-receipt")
            ->assertStatus(409)
            ->assertJsonPath('error', 'Receipt already generated')
            ->assertJsonPath('receipt_document_id', $existingReceiptId);
    }

    public function test_generate_receipt_scoped_to_company(): void
    {
        $otherCompany = Company::factory()->create(['code' => 'PGG']);
        $otherUser = User::factory()->create([
            'role' => 'admin',
            'company_id' => $otherCompany->id,
        ]);

        Sanctum::actingAs($this->user);
        $payment = $this->createUnreceiptedPayment(50);

        Sanctum::actingAs($otherUser);
        $this->postJson("/api/payments/{$payment->id}/generate-receipt")
            ->assertNotFound();
    }

    private function createUnreceiptedPayment(float $amount): \App\Models\Payment
    {
        $invoice = $this->workflow->issue($this->workflow->createDraft([
            'company_id' => $this->company->id,
            'document_type' => 'invoice',
            'items' => [['description' => 'Service rendered', 'quantity' => 1, 'unit_price' => $amount]],
        ])->id);

        return $this->workflow->recordPayment([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'amount' => $amount,
            'reference_number' => 'TXN-'.uniqid(),
            'allocations' => [['document_id' => $invoice->id, 'amount' => $amount]],
            'create_official_receipt' => false,
        ]);
    }
}
