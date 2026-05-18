<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_logo_stamp_and_signature(): void
    {
        Storage::fake('public');
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        foreach (['logo', 'stamp', 'signature'] as $kind) {
            $response = $this->postJson("/api/companies/{$company->id}/branding/{$kind}", [
                'file' => UploadedFile::fake()->image("{$kind}.png", 200, 100),
            ]);

            $response->assertOk()
                ->assertJsonPath('kind', $kind)
                ->assertJsonStructure(['kind', 'path', 'url', 'company']);

            $path = $response->json('path');
            Storage::disk('public')->assertExists($path);
        }

        $fresh = $company->fresh();
        $this->assertNotNull($fresh->logo_path);
        $this->assertNotNull($fresh->stamp_path);
        $this->assertNotNull($fresh->signature_path);
    }

    public function test_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/companies/{$company->id}/branding/logo", [
            'file' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertUnprocessable();
    }

    public function test_upload_rejects_invalid_kind(): void
    {
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/companies/{$company->id}/branding/header", [
            'file' => UploadedFile::fake()->image('header.png'),
        ])->assertNotFound();
    }

    public function test_user_cannot_upload_to_other_company(): void
    {
        Storage::fake('public');
        $companyA = Company::factory()->wehdah()->create();
        $companyB = Company::factory()->nasCeria()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $companyA->id]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/companies/{$companyB->id}/branding/logo", [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])->assertForbidden();
    }

    public function test_delete_clears_path_and_file(): void
    {
        Storage::fake('public');
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->postJson("/api/companies/{$company->id}/branding/logo", [
            'file' => UploadedFile::fake()->image('logo.png'),
        ])->assertOk();

        $path = $company->fresh()->logo_path;
        Storage::disk('public')->assertExists($path);

        $this->deleteJson("/api/companies/{$company->id}/branding/logo")->assertOk();

        $this->assertNull($company->fresh()->logo_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_brand_color_validation_rejects_non_hex(): void
    {
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/companies/{$company->id}", [
            'brand_primary' => 'red',
        ])->assertUnprocessable();
    }

    public function test_brand_colors_persist(): void
    {
        $company = Company::factory()->wehdah()->create();
        $admin = User::factory()->create(['role' => 'admin', 'company_id' => $company->id]);
        Sanctum::actingAs($admin);

        $this->patchJson("/api/companies/{$company->id}", [
            'brand_primary' => '#8B0000',
            'brand_secondary' => '#FFE4E1',
            'brand_accent' => '#660000',
        ])->assertOk()
            ->assertJsonPath('brand_primary', '#8B0000');

        $fresh = $company->fresh();
        $this->assertSame('#8B0000', $fresh->brand_primary);
        $this->assertSame('#FFE4E1', $fresh->brand_secondary);
        $this->assertSame('#660000', $fresh->brand_accent);
    }
}
