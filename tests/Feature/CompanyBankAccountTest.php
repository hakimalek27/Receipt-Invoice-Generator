<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyBankAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_create_update_and_delete_bank_accounts(): void
    {
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/companies/{$company->id}/bank-accounts")
            ->assertOk()
            ->assertJsonCount(0);

        $created = $this->postJson("/api/companies/{$company->id}/bank-accounts", [
            'bank_name' => 'Maybank',
            'account_number' => '5121-1234-5678',
            'account_holder' => 'WEHDAH SOLUTION',
            'is_primary' => true,
            'sort_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('bank_name', 'Maybank')
            ->json();

        $this->patchJson("/api/companies/{$company->id}/bank-accounts/{$created['id']}", [
            'account_holder' => 'WEHDAH SOLUTION (M) SDN BHD',
        ])->assertOk()
            ->assertJsonPath('account_holder', 'WEHDAH SOLUTION (M) SDN BHD');

        $this->deleteJson("/api/companies/{$company->id}/bank-accounts/{$created['id']}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, CompanyBankAccount::where('company_id', $company->id)->count());
    }

    public function test_only_one_primary_is_kept_when_promoting_a_new_one(): void
    {
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $first = $this->postJson("/api/companies/{$company->id}/bank-accounts", [
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '187',
            'is_primary' => true,
        ])->json();

        $second = $this->postJson("/api/companies/{$company->id}/bank-accounts", [
            'bank_name' => 'Bank Islam',
            'account_number' => '121',
            'is_primary' => true,
        ])->json();

        $this->assertFalse((bool) CompanyBankAccount::find($first['id'])->is_primary);
        $this->assertTrue((bool) CompanyBankAccount::find($second['id'])->is_primary);
    }

    public function test_admin_cannot_access_other_company_bank_accounts(): void
    {
        $companyA = Company::factory()->wehdah()->create();
        $companyB = Company::factory()->nasCeria()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $companyA->id]);
        Sanctum::actingAs($admin);

        $this->getJson("/api/companies/{$companyB->id}/bank-accounts")->assertForbidden();
        $this->postJson("/api/companies/{$companyB->id}/bank-accounts", [
            'bank_name' => 'Hack',
            'account_number' => '0',
        ])->assertForbidden();
    }
}
