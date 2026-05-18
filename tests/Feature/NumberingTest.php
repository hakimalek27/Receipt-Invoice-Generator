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

    private array $typeCodes = [
        'invoice' => 'INV', 'quotation' => 'Q', 'official_receipt' => 'REC',
        'delivery_order' => 'DO',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->numbering = app(NumberingService::class);
    }

    private function setupPolicies(int $companyId, string $code): void
    {
        foreach ($this->typeCodes as $type => $short) {
            NumberingPolicy::create([
                'company_id' => $companyId,
                'document_type' => $type,
                'prefix' => $code . '-' . $short,
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

        $this->assertEquals('WS-INV-2026-00001', $wsNum);
        $this->assertEquals('PGG-INV-2026-00001', $pggNum);
        $this->assertNotEquals($wsNum, $pggNum);
    }

    public function test_sequences_dont_leak_between_companies(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $ncs = Company::factory()->create(['code' => 'NCS']);

        $this->setupPolicies($ws->id, 'WS');
        $this->setupPolicies($ncs->id, 'NCS');

        for ($i = 0; $i < 5; $i++) {
            $this->numbering->allocate($ws->id, 'invoice');
        }

        $ncsFirst = $this->numbering->allocate($ncs->id, 'invoice');
        $this->assertEquals('NCS-INV-2026-00001', $ncsFirst);
    }

    public function test_draft_has_no_official_number(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $preview = $this->numbering->preview($ws->id, 'invoice');
        $this->assertStringContainsString('#', $preview);

        $counter = SequenceCounter::forCompany($ws->id)
            ->forType('invoice')->forYear(2026)->first();
        $this->assertNull($counter);
    }

    public function test_number_preview_does_not_reserve_sequence(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        for ($i = 0; $i < 10; $i++) {
            $preview = $this->numbering->preview($ws->id, 'invoice');
            $this->assertEquals('WS-INV-2026-#####', $preview);
        }

        $this->assertDatabaseMissing('sequence_counters', [
            'company_id' => $ws->id, 'document_type' => 'invoice',
        ]);

        $first = $this->numbering->allocate($ws->id, 'invoice');
        $this->assertEquals('WS-INV-2026-00001', $first);
    }

    public function test_concurrent_issue_allocation_simulation(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $numbers = [];
        for ($i = 0; $i < 20; $i++) {
            $numbers[] = $this->numbering->allocate($ws->id, 'invoice');
        }

        $this->assertCount(20, array_unique($numbers));
        $this->assertEquals('WS-INV-2026-00001', $numbers[0]);
        $this->assertEquals('WS-INV-2026-00020', $numbers[19]);
    }

    public function test_year_rollover_creates_new_counter(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $num2026 = $this->numbering->allocate($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-INV-2026-00001', $num2026);

        $num2027 = $this->numbering->allocate($ws->id, 'invoice', 2027);
        $this->assertEquals('WS-INV-2027-00001', $num2027);

        $this->assertEquals(1, SequenceCounter::forCompany($ws->id)
            ->forType('invoice')->forYear(2026)->first()->current_sequence);
        $this->assertEquals(1, SequenceCounter::forCompany($ws->id)
            ->forType('invoice')->forYear(2027)->first()->current_sequence);
    }

    public function test_different_document_types_have_separate_sequences(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->setupPolicies($ws->id, 'WS');

        $inv1 = $this->numbering->allocate($ws->id, 'invoice');
        $q1 = $this->numbering->allocate($ws->id, 'quotation');
        $rec1 = $this->numbering->allocate($ws->id, 'official_receipt');

        $this->assertEquals('WS-INV-2026-00001', $inv1);
        $this->assertEquals('WS-Q-2026-00001', $q1);
        $this->assertEquals('WS-REC-2026-00001', $rec1);

        $inv2 = $this->numbering->allocate($ws->id, 'invoice');
        $this->assertEquals('WS-INV-2026-00002', $inv2);
    }

    public function test_allocate_fails_without_active_policy(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        $this->expectException(\RuntimeException::class);
        $this->numbering->allocate($ws->id, 'invoice');
    }

    public function test_format_examples_for_phase_0_5_defaults(): void
    {
        $ws = Company::factory()->create(['code' => 'WS']);
        NumberingPolicy::create([
            'company_id' => $ws->id, 'document_type' => 'invoice',
            'prefix' => 'WS-INV', 'separator' => '-', 'year_token' => '{YYYY}',
            'sequence_padding' => 5, 'reset_policy' => 'yearly', 'is_active' => true,
        ]);

        $preview = $this->numbering->preview($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-INV-2026-#####', $preview);

        $allocated = $this->numbering->allocate($ws->id, 'invoice', 2026);
        $this->assertEquals('WS-INV-2026-00001', $allocated);
    }
}
