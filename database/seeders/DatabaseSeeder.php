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

    private array $typeCodes = [
        'invoice' => 'INV', 'quotation' => 'Q', 'official_receipt' => 'REC',
        'delivery_order' => 'DO', 'cash_bill' => 'CB', 'credit_note' => 'CN',
        'debit_note' => 'DN', 'purchase_order' => 'PO', 'payment_voucher' => 'PV',
        'proforma_invoice' => 'PI',
    ];

    private function seedPolicies(int $companyId, string $code): void
    {
        foreach ($this->documentTypes as $type) {
            NumberingPolicy::create([
                'company_id' => $companyId,
                'document_type' => $type,
                'prefix' => $code . '-' . $this->typeCodes[$type],
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
        $wehdah = Company::factory()->wehdah()->create();
        $nasCeria = Company::factory()->nasCeria()->create();
        $persada = Company::factory()->persada()->create();

        $this->seedPolicies($wehdah->id, 'WS');
        $this->seedPolicies($nasCeria->id, 'NCS');
        $this->seedPolicies($persada->id, 'PGG');

        $wehdah->bankAccounts()->create([
            'bank_name' => 'Hong Leong Islamic',
            'account_number' => '18701038380',
            'account_holder' => 'WEHDAH SOLUTION',
            'is_primary' => true,
            'sort_order' => 1,
        ]);
        $wehdah->bankAccounts()->create([
            'bank_name' => 'Bank Islam',
            'account_number' => '12113010769313',
            'account_holder' => 'WEHDAH SOLUTION',
            'sort_order' => 2,
        ]);

        User::factory()->create([
            'name' => 'Super Admin', 'email' => 'super@example.com',
            'role' => 'super_admin', 'company_id' => null,
        ]);
        User::factory()->create([
            'name' => 'Wehdah Admin', 'email' => 'admin@wehdah.test',
            'role' => 'admin', 'company_id' => $wehdah->id,
        ]);
        User::factory()->create([
            'name' => 'Wehdah User', 'email' => 'user@wehdah.test',
            'role' => 'user', 'company_id' => $wehdah->id,
        ]);
        User::factory()->create([
            'name' => 'NAS Admin', 'email' => 'admin@nasceria.test',
            'role' => 'admin', 'company_id' => $nasCeria->id,
        ]);
        User::factory()->create([
            'name' => 'Persada Admin', 'email' => 'admin@persada.test',
            'role' => 'admin', 'company_id' => $persada->id,
        ]);
    }
}
