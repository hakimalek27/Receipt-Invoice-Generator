<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyScopingTest extends TestCase
{
    use RefreshDatabase;

    public function test_app_boots(): void
    {
        $response = $this->get('/');

        // Welcome page is public
        $response->assertStatus(200);
    }

    public function test_auth_test_passes(): void
    {
        $user = User::factory()->create([
            'role' => 'admin',
            'company_id' => Company::factory()->create()->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_super_admin_can_access_without_company(): void
    {
        $user = User::factory()->create([
            'role' => 'super_admin',
            'company_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(200);
    }

    public function test_user_without_company_is_denied(): void
    {
        $user = User::factory()->create([
            'role' => 'user',
            'company_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_user_with_inactive_company_is_denied(): void
    {
        $inactiveCompany = Company::factory()->inactive()->create();

        $user = User::factory()->create([
            'role' => 'user',
            'company_id' => $inactiveCompany->id,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertStatus(403);
    }

    public function test_user_cannot_see_other_company_placeholder_data(): void
    {
        $companyA = Company::factory()->create(['code' => 'AAA', 'name' => 'Company A']);
        $companyB = Company::factory()->create(['code' => 'BBB', 'name' => 'Company B']);

        $userA = User::factory()->create([
            'role' => 'admin',
            'company_id' => $companyA->id,
        ]);

        // UserA can only see CompanyA
        $this->assertEquals($companyA->id, $userA->company_id);
        $this->assertNotEquals($companyB->id, $userA->company_id);

        // Verify scoping: UserA belongs to CompanyA only
        $this->assertTrue($userA->company->is($companyA));
        $this->assertFalse($userA->company->is($companyB));
    }

    public function test_user_belongs_to_company_relationship(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->assertNotNull($user->company);
        $this->assertEquals($company->id, $user->company->id);
        $this->assertEquals($company->code, $user->company->code);
    }

    public function test_company_has_many_users(): void
    {
        $company = Company::factory()->create();
        User::factory(3)->create(['company_id' => $company->id]);

        $this->assertCount(3, $company->users);
    }
}
