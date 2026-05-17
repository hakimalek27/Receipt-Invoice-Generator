<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\SequenceCounter;
use App\Services\NumberingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingTest extends TestCase
{
    use RefreshDatabase;

    protected NumberingService $numbering;

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbering = app(NumberingService::class);
    }

    private function setupPolicies(int $companyId, string $code): void
    {
        $types = ['invoice', 'quotation', 'official_receipt', 'delivery_order'];
        foreach ($types as $type) {
            NumberingPolicy::create([
                'company_id' => $companyId,
                'document_type' => $type,
                'prefix' => $code,
                'separator' => '-',
                'year_token' => '{YYYY}',
                'sequence_padding' => 5,
                'reset_policy' => 'yearly',
                'is_active' => true,
            ]);
        }
    }

    public function test_per_company_sequence_isolation(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $pgg = Company::factory()->create(['code' => 'PGG']);

        $this->setupPolicies($ws->id, 'WS');
        $this->setupPolicies($pgg->id, 'PGG');

        $wsNum = $this->numbering->allocate($ws->id, 'invoice');
        $pggNum = $this->numbering->allocate($pgg->id, 'invoice');

        $this->assertEquals('WS-2026-00001', $wsNum);
        $this->assertEquals('PGG-2026-00001', $pggNum);
        $this->assertNotEquals($wsNum, $pggNum);
    }

    public function test_sequences_dont_leak_between_companies(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $ncs = Company::factory()->create(['code' => 'NCS']);

        $this->setupPolicies($ws->id, 'WS');
        $this->setupPolicies($ncs->id, 'NCS');

        // Allocate 5 invoices for WS
        for ($i = 0; $i < 5; $i++) {
            $this->numbering->allocate($ws->id, 'invoice');
        }

        // NCS should still get 00001
        $ncsFirst = $this->numbering->allocate($ncs->id, 'invoice');
        $this->assertEquals('NCS-2026-00001', $ncsFirst);
    }

    public function test_draft_has_no_official_number(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        // Preview returns format-only, no allocation
        $preview = $this->numbering->preview($ws->id, 'invoice');
        $this->assertStringContainsString('#', $preview, 'Preview must use placeholder hashes');

        // No sequence counter created yet
        $counter = SequenceCounter::forCompany($ws->id)
            ->forType('invoice')
            ->forYear(2026)
            ->first();
        $this->assertNull($counter, 'No counter should exist before first issue');
    }

    public function test_number_preview_does_not_reserve_sequence(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        // Preview 10 times — should never create a counter or change state
        for ($i = 0; $i < 10; $i++) {
            $preview = $this->numbering->preview($ws->id, 'invoice');
            $this->assertEquals('WS-2026-#####', $preview);
        }

        // No counter exists
        $this->assertDatabaseMissing('sequence_counters', [
            'company_id' => $ws->id,
            'document_type' => 'invoice',
        ]);

        // First real allocation gives 00001
        $first = $this->numbering->allocate($ws->id, 'invoice');
        $this->assertEquals('WS-2026-00001', $first);
    }

    public function test_concurrent_issue_allocation_simulation(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $numbers = [];
        // Simulate 20 allocations (sequential is fine for SQLite; row lock prevents real conflicts)
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = $this->numbering->allocate($ws->id, 'invoice');
        }

        // All numbers must be unique
        $this->assertCount(20, array_unique($numbers));
        // First and last
        $this->assertEquals('WS-2026-00001', $numbers[0]);
        $this->assertEquals('WS-2026-00020', $numbers[19]);
    }

    public function test_year_rollover_creates_new_counter(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        // Allocate in 2026
        $num2026 = $this->numbering->allocate($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-2026-00001', $num2026);

        // Allocate in 2027 — should start fresh
        $num2027 = $this->numbering->allocate($ws->id, 'invoice', 2027);
        $this->assertEquals('WS-2027-00001', $num2027);

        // 2026 counter unchanged
        $counter2026 = SequenceCounter::forCompany($ws->id)
            ->forType('invoice')
            ->forYear(2026)
            ->first();
        $this->assertEquals(1, $counter2026->current_sequence);

        // 2027 counter exists and is at 1
        $counter2027 = SequenceCounter::forCompany($ws->id)
            ->forType('invoice')
            ->forYear(2027)
            ->first();
        $this->assertEquals(1, $counter2027->current_sequence);
    }

    public function test_different_document_types_have_separate_sequences(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $inv1 = $this->numbering->allocate($ws->id, 'invoice');
        $q1 = $this->numbering->allocate($ws->id, 'quotation');
        $rec1 = $this->numbering->allocate($ws->id, 'official_receipt');

        $this->assertEquals('WS-2026-00001', $inv1);
        $this->assertEquals('WS-2026-00001', $q1);
        $this->assertEquals('WS-2026-00001', $rec1);

        // Invoice second allocation
        $inv2 = $this->numbering->allocate($ws->id, 'invoice');
        $this->assertEquals('WS-2026-00002', $inv2);
    }

    public function test_allocate_fails_without_active_policy(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        // No policies created

        $this->expectException(\RuntimeException::class);
        $this->numbering->allocate($ws->id, 'invoice');
    }

    public function test_format_examples_for_phase_0_5_defaults(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        NumberingPolicy::create([
            'company_id' => $ws->id,
            'document_type' => 'invoice',
            'prefix' => 'WS',
            'separator' => '-',
            'year_token' => '{YYYY}',
            'sequence_padding' => 5,
            'reset_policy' => 'yearly',
            'is_active' => true,
        ]);

        $preview = $this->numbering->preview($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-2026-#####', $preview);

        $allocated = $this->numbering->allocate($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-2026-00001', $allocated);
    }
}
