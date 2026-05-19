<?php

namespace Tests\Feature;

use App\Console\Commands\WehdahApplyDefaultsCommand;
use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\NumberingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WehdahApplyDefaultsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_fills_blank_company_fields(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'name' => 'Wehdah Solution',
            'address' => null,
            'address_line_2' => null,
            'phone' => null,
            'email' => null,
            'registration_number' => null,
        ]);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])
            ->assertSuccessful();

        $company->refresh();
        $this->assertSame('Wisma UOA II, Unit No: 15-13A,', $company->address);
        $this->assertSame('UOA Business Centre, Jalan Pinang,', $company->address_line_2);
        $this->assertSame('+6017-3123415', $company->phone);
        $this->assertSame('wehdahsolution@gmail.com', $company->email);
        $this->assertSame('202103190949 (PG0514579-H)', $company->registration_number);
    }

    public function test_command_skips_user_set_values_without_force(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'name' => 'Wehdah Solution',
            'phone' => '+6011-1234567',          // user-set, not a placeholder
            'email' => 'real@wehdah.com',
        ]);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])
            ->assertSuccessful();

        $company->refresh();
        $this->assertSame('+6011-1234567', $company->phone);
        $this->assertSame('real@wehdah.com', $company->email);
    }

    public function test_command_overwrites_faker_placeholders_with_force(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'name' => 'Wehdah Solution',
            'phone' => '+12174457840',                            // US faker format
            'email' => 'alicia.nikolaus@gulgowski.com',           // faker surname domain
        ]);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS', '--force' => true])
            ->assertSuccessful();

        $company->refresh();
        $this->assertSame('+6017-3123415', $company->phone);
        $this->assertSame('wehdahsolution@gmail.com', $company->email);
    }

    public function test_command_creates_missing_bank_accounts_for_ws(): void
    {
        $company = Company::factory()->create(['code' => 'WS', 'name' => 'Wehdah Solution']);
        $this->assertSame(0, $company->bankAccounts()->count());

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])
            ->assertSuccessful();

        $this->assertSame(2, $company->bankAccounts()->count());
        $this->assertDatabaseHas('company_bank_accounts', [
            'company_id' => $company->id,
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380',
        ]);
        $this->assertDatabaseHas('company_bank_accounts', [
            'company_id' => $company->id,
            'bank_name' => 'Bank Islam',
            'account_number' => '12113010769313',
        ]);
    }

    public function test_command_creates_all_10_numbering_policies_for_ws(): void
    {
        $company = Company::factory()->create(['code' => 'WS', 'name' => 'Wehdah Solution']);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])
            ->assertSuccessful();

        $this->assertSame(10, NumberingPolicy::where('company_id', $company->id)->count());
        $this->assertDatabaseHas('numbering_policies', [
            'company_id' => $company->id,
            'document_type' => 'invoice',
            'prefix' => 'WS-INV',
        ]);
    }

    public function test_command_is_idempotent_when_run_twice(): void
    {
        $company = Company::factory()->create(['code' => 'WS', 'name' => 'Wehdah Solution']);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])->assertSuccessful();
        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS'])->assertSuccessful();

        $this->assertSame(2, $company->bankAccounts()->count());
        $this->assertSame(10, NumberingPolicy::where('company_id', $company->id)->count());
    }

    public function test_dry_run_does_not_write(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'name' => 'Wehdah Solution',
            'phone' => null,
        ]);

        $this->artisan('wehdah:apply-defaults', ['--code' => 'WS', '--dry-run' => true])
            ->assertSuccessful();

        $company->refresh();
        $this->assertNull($company->phone);
        $this->assertSame(0, $company->bankAccounts()->count());
    }

    public function test_unknown_code_returns_failure(): void
    {
        $this->artisan('wehdah:apply-defaults', ['--code' => 'XYZ'])
            ->assertFailed();
    }

    public function test_runs_against_all_four_companies_by_default(): void
    {
        Company::factory()->create(['code' => 'WS', 'name' => 'Wehdah Solution']);
        Company::factory()->create(['code' => 'NCS', 'name' => 'Nas Ceria Services']);
        Company::factory()->create(['code' => 'PGG', 'name' => 'Persada Gemilang Global']);
        Company::factory()->create(['code' => 'VD', 'name' => 'Virtue Damsel']);

        $this->artisan('wehdah:apply-defaults')->assertSuccessful();

        // Just confirm numbering policies exist for all four — proves the loop ran.
        foreach (['WS', 'NCS', 'PGG', 'VD'] as $code) {
            $this->assertTrue(
                NumberingPolicy::whereHas('company', fn ($q) => $q->where('code', $code))->exists(),
                "Expected numbering policies for {$code}"
            );
        }
    }
}
