<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnboardingBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_onboarding_props_when_profile_incomplete(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'phone' => null,
            'email' => null,
            'address_line_2' => null,
            'logo_path' => null,
            'stamp_path' => null,
            'signature_path' => null,
        ]);
        $user = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Dashboard')
                ->where('onboarding.complete', false)
                ->has('onboarding.missing')
                ->where('onboarding.first_tab', 'company')
            );
    }

    public function test_dashboard_marks_onboarding_complete_when_all_filled(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'phone' => '+6017-3123415',
            'email' => 'real@wehdah.test',
            'address_line_2' => 'UOA Business Centre',
            'logo_path' => 'companies/1/logo.png',
            'stamp_path' => 'companies/1/stamp.png',
            'signature_path' => 'companies/1/signature.png',
        ]);
        CompanyBankAccount::create([
            'company_id' => $company->id,
            'bank_name' => 'Bank Islam',
            'account_number' => '12345',
            'sort_order' => 1,
        ]);

        $user = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Dashboard')
                ->where('onboarding.complete', true)
                ->where('onboarding.missing', [])
            );
    }

    public function test_onboarding_detects_faker_email_as_placeholder(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'email' => 'alicia.nikolaus@gulgowski.com',
            'phone' => '+6017-3123415',
            'address_line_2' => 'Line 2',
            'logo_path' => 'x', 'stamp_path' => 'y', 'signature_path' => 'z',
        ]);
        CompanyBankAccount::create([
            'company_id' => $company->id, 'bank_name' => 'B', 'account_number' => '1',
            'sort_order' => 1,
        ]);

        $checklist = $company->onboardingChecklist();

        $this->assertFalse($checklist['complete']);
        $labels = array_column($checklist['missing'], 'label');
        $this->assertContains('Company email', $labels);
    }

    public function test_onboarding_first_tab_points_to_branding_when_only_branding_missing(): void
    {
        $company = Company::factory()->create([
            'code' => 'WS',
            'phone' => '+6017-3123415',
            'email' => 'real@wehdah.test',
            'address_line_2' => 'UOA',
            'logo_path' => null,            // missing → branding
            'stamp_path' => null,
            'signature_path' => null,
        ]);
        CompanyBankAccount::create([
            'company_id' => $company->id, 'bank_name' => 'B', 'account_number' => '1',
            'sort_order' => 1,
        ]);

        $checklist = $company->onboardingChecklist();
        $this->assertSame('branding', $checklist['first_tab']);
    }
}
