<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\NumberingPolicy;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    private array $documentTypes = [
        'invoice', 'quotation', 'official_receipt', 'delivery_order',
        'cash_bill', 'credit_note', 'debit_note', 'purchase_order',
        'payment_voucher', 'proforma_invoice',
    ];

    private function seedPolicies(int $companyId, string $code): void
    {
        $shortCodes = [
            'invoice' => 'INV', 'quotation' => 'Q', 'official_receipt' => 'REC',
            'delivery_order' => 'DO', 'cash_bill' => 'CB', 'credit_note' => 'CN',
            'debit_note' => 'DN', 'purchase_order' => 'PO', 'payment_voucher' => 'PV',
            'proforma_invoice' => 'PI',
        ];

        foreach ($this->documentTypes as $type) {
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

    public function run(): void
    {
        // Seed canonical companies
        $wehdah = Company::factory()->wehdah()->create();
        $nasCeria = Company::factory()->nasCeria()->create();
        $persada = Company::factory()->persada()->create();

        // Seed numbering policies
        $this->seedPolicies($wehdah->id, 'WS');
        $this->seedPolicies($nasCeria->id, 'NCS');
        $this->seedPolicies($persada->id, 'PGG');

        // Super admin
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@example.com',
            'role' => 'super_admin',
            'company_id' => null,
        ]);

        // Wehdah admin
        User::factory()->create([
            'name' => 'Wehdah Admin',
            'email' => 'admin@wehdah.test',
            'role' => 'admin',
            'company_id' => $wehdah->id,
        ]);

        // Wehdah user
        User::factory()->create([
            'name' => 'Wehdah User',
            'email' => 'user@wehdah.test',
            'role' => 'user',
            'company_id' => $wehdah->id,
        ]);

        // Nas Ceria admin
        User::factory()->create([
            'name' => 'NAS Admin',
            'email' => 'admin@nasceria.test',
            'role' => 'admin',
            'company_id' => $nasCeria->id,
        ]);

        // Persada admin
        User::factory()->create([
            'name' => 'Persada Admin',
            'email' => 'admin@persada.test',
            'role' => 'admin',
            'company_id' => $persada->id,
        ]);
    }
}
