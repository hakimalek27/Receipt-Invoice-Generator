<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::factory()->wehdah()->create();
        $this->user = User::factory()->create([
            'role' => 'admin',
            'company_id' => $this->company->id,
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_customer_csv_import_inserts_new_rows_and_skips_duplicates(): void
    {
        Customer::create([
            'company_id' => $this->company->id,
            'name' => 'Existing Co',
            'is_active' => true,
        ]);

        $csv = "name,email,phone\n"
            ."Acme Sdn Bhd,acme@test.local,012-1234567\n"
            ."Existing Co,dup@test.local,011-9999999\n"
            ."Beta Corp,beta@test.local,019-2222222\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->postJson('/api/customers/import', ['file' => $file])
            ->assertOk()
            ->json();

        $this->assertSame(2, $response['inserted']);
        $this->assertSame(1, $response['skipped']);
        $this->assertCount(0, $response['errors']);

        $this->assertDatabaseHas('customers', ['name' => 'Acme Sdn Bhd', 'company_id' => $this->company->id]);
        $this->assertDatabaseHas('customers', ['name' => 'Beta Corp', 'company_id' => $this->company->id]);
    }

    public function test_customer_csv_import_returns_per_row_errors_on_invalid_email(): void
    {
        $csv = "name,email\n"
            ."Valid Co,good@test.local\n"
            ."Bad Co,not-an-email\n";

        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $response = $this->postJson('/api/customers/import', ['file' => $file])
            ->assertOk()
            ->json();

        $this->assertSame(1, $response['inserted']);
        $this->assertCount(1, $response['errors']);
        $this->assertSame(3, $response['errors'][0]['row']);
        $this->assertStringContainsString('email', strtolower($response['errors'][0]['message']));

        $this->assertDatabaseHas('customers', ['name' => 'Valid Co']);
        $this->assertDatabaseMissing('customers', ['name' => 'Bad Co']);
    }

    public function test_customer_csv_import_rejects_file_without_name_header(): void
    {
        $csv = "email,phone\nfoo@test.local,012-1234567\n";
        $file = UploadedFile::fake()->createWithContent('customers.csv', $csv);

        $this->postJson('/api/customers/import', ['file' => $file])
            ->assertStatus(422)
            ->assertJsonPath('error', 'CSV must include a "name" header column.');
    }

    public function test_product_csv_import_inserts_rows(): void
    {
        $csv = "name,sku,default_price,uom\n"
            ."Banner 4x8,BNR-4x8,180.00,pcs\n"
            ."Stand,STD-001,85.00,pcs\n";

        $file = UploadedFile::fake()->createWithContent('products.csv', $csv);

        $response = $this->postJson('/api/products/import', ['file' => $file])
            ->assertOk()
            ->json();

        $this->assertSame(2, $response['inserted']);
        $this->assertSame(0, $response['skipped']);
        $this->assertDatabaseHas('products', ['name' => 'Banner 4x8', 'sku' => 'BNR-4x8', 'company_id' => $this->company->id]);
    }
}
