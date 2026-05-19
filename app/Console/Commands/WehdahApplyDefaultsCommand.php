<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompanyBankAccount;
use App\Models\NumberingPolicy;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent fixer for company profile data, branding, bank accounts,
 * and numbering policies.
 *
 * Use when production was seeded with faker placeholders (e.g. company
 * phone shows '+12174457840' instead of the real number) and the WS
 * documents now render with bogus contact info on the PDF header.
 *
 * Usage:
 *   php artisan wehdah:apply-defaults                    # only fills blank fields
 *   php artisan wehdah:apply-defaults --force            # overwrites placeholders too
 *   php artisan wehdah:apply-defaults --code=NCS         # one company
 */
class WehdahApplyDefaultsCommand extends Command
{
    protected $signature = 'wehdah:apply-defaults
        {--code= : Limit to a single company code (WS, NCS, PGG, VD). Default: all four.}
        {--force : Overwrite existing values that look like faker placeholders, not just blanks.}
        {--dry-run : Print intended changes without writing.}';

    protected $description = 'Apply real-world default profile + branding + bank accounts + numbering policies to seeded companies.';

    public const DEFAULTS = [
        'WS' => [
            'name' => 'Wehdah Solution',
            'code' => 'WS',
            'address' => 'Wisma UOA II, Unit No: 15-13A,',
            'address_line_2' => 'UOA Business Centre, Jalan Pinang,',
            'city' => 'Kuala Lumpur',
            'state' => 'Wilayah Persekutuan',
            'postcode' => '50450',
            'country' => 'MY',
            'phone' => '+6017-3123415',
            'email' => 'wehdahsolution@gmail.com',
            'registration_number' => '202103190949 (PG0514579-H)',
            'brand_primary' => '#002060',
            'brand_secondary' => '#f4f7fa',
            'brand_accent' => '#1F3A5F',
            'is_active' => true,
            '_bank_accounts' => [
                ['bank_name' => 'Hong Leong Islamic', 'account_number' => '18701038380', 'account_holder' => 'WEHDAH SOLUTION', 'is_primary' => true, 'sort_order' => 1],
                ['bank_name' => 'Bank Islam', 'account_number' => '12113010769313', 'account_holder' => 'WEHDAH SOLUTION', 'sort_order' => 2],
            ],
            '_numbering' => ['INV', 'Q', 'REC', 'DO', 'CB', 'CN', 'DN', 'PO', 'PV', 'PI'],
        ],
        'NCS' => [
            'name' => 'Nas Ceria Services',
            'code' => 'NCS',
            'address' => '14-1, 1st Floor, Jalan Wangsa Budi 1,',
            'address_line_2' => 'Taman Wangsa Melawati,',
            'city' => 'Kuala Lumpur',
            'postcode' => '53300',
            'country' => 'MY',
            'registration_number' => '003035718-X',
            'brand_primary' => '#1F3A5F',
            'brand_secondary' => '#F4ECD8',
            'brand_accent' => '#C0A062',
            'is_active' => true,
            '_numbering' => ['INV', 'Q', 'REC', 'DO'],
        ],
        'PGG' => [
            'name' => 'Persada Gemilang Global',
            'code' => 'PGG',
            'brand_primary' => '#5d3a9b',
            'brand_secondary' => '#efeaf7',
            'brand_accent' => '#3f2872',
            'is_active' => true,
            '_numbering' => ['INV', 'Q', 'REC', 'DO'],
        ],
        'VD' => [
            'name' => 'Virtue Damsel',
            'code' => 'VD',
            'brand_primary' => '#E67E22',
            'brand_secondary' => '#FBEEE6',
            'brand_accent' => '#16A085',
            'is_active' => true,
            '_numbering' => ['INV', 'Q', 'REC', 'DO'],
        ],
    ];

    private const TYPE_CODES = [
        'INV' => 'invoice', 'Q' => 'quotation', 'REC' => 'official_receipt', 'DO' => 'delivery_order',
        'CB' => 'cash_bill', 'CN' => 'credit_note', 'DN' => 'debit_note',
        'PO' => 'purchase_order', 'PV' => 'payment_voucher', 'PI' => 'proforma_invoice',
    ];

    public function handle(): int
    {
        $codes = $this->option('code')
            ? [strtoupper($this->option('code'))]
            : array_keys(self::DEFAULTS);

        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($codes as $code) {
            if (! isset(self::DEFAULTS[$code])) {
                $this->error("Unknown company code: {$code}. Allowed: ".implode(',', array_keys(self::DEFAULTS)));
                return self::FAILURE;
            }
            $this->applyCompany($code, $force, $dryRun);
        }

        return self::SUCCESS;
    }

    private function applyCompany(string $code, bool $force, bool $dryRun): void
    {
        $defaults = self::DEFAULTS[$code];
        $bankAccounts = $defaults['_bank_accounts'] ?? [];
        $numbering = $defaults['_numbering'] ?? [];
        unset($defaults['_bank_accounts'], $defaults['_numbering']);

        $company = Company::where('code', $code)->first();
        if (! $company) {
            $this->warn("[{$code}] No company row found — skipping. (Run db:seed first.)");
            return;
        }

        $this->info("[{$code}] Inspecting {$company->name} (#{$company->id})");
        $changes = [];

        foreach ($defaults as $field => $expected) {
            $current = $company->{$field};
            $shouldUpdate = false;

            if ($current === null || $current === '' || $current === false) {
                $shouldUpdate = true;
            } elseif ($force && $this->looksLikePlaceholder($current, $field)) {
                $shouldUpdate = true;
            }

            if ($shouldUpdate) {
                $changes[$field] = ['was' => $current, 'now' => $expected];
            }
        }

        if (! empty($changes)) {
            foreach ($changes as $f => $c) {
                $this->line(sprintf('  - %s: %s  →  %s',
                    $f,
                    var_export($c['was'], true),
                    var_export($c['now'], true)));
            }
            if (! $dryRun) {
                DB::transaction(function () use ($company, $changes) {
                    foreach ($changes as $f => $c) {
                        $company->{$f} = $c['now'];
                    }
                    $company->save();
                });
                $this->info('  ✓ Company row updated.');
            }
        } else {
            $this->line('  - Company row already complete.');
        }

        // Bank accounts (only for WS or any code that defines them)
        if (! empty($bankAccounts)) {
            foreach ($bankAccounts as $spec) {
                $existing = CompanyBankAccount::where('company_id', $company->id)
                    ->where('account_number', $spec['account_number'])
                    ->first();
                if (! $existing) {
                    if (! $dryRun) {
                        $company->bankAccounts()->create($spec);
                    }
                    $this->info(sprintf('  + Bank account: %s %s', $spec['bank_name'], $spec['account_number']));
                }
            }
        }

        // Numbering policies
        foreach ($numbering as $shortCode) {
            $type = self::TYPE_CODES[$shortCode] ?? null;
            if (! $type) continue;
            $exists = NumberingPolicy::where('company_id', $company->id)
                ->where('document_type', $type)
                ->exists();
            if (! $exists) {
                if (! $dryRun) {
                    NumberingPolicy::create([
                        'company_id' => $company->id,
                        'document_type' => $type,
                        'prefix' => $code.'-'.$shortCode,
                        'separator' => '-',
                        'year_token' => '{YYYY}',
                        'sequence_padding' => 5,
                        'reset_policy' => 'yearly',
                        'is_active' => true,
                    ]);
                }
                $this->info("  + Numbering policy: {$code}-{$shortCode} ({$type})");
            }
        }

        $this->line('');
    }

    /**
     * Detect classic faker placeholders so --force can overwrite them
     * without trashing genuine user-set values.
     */
    private function looksLikePlaceholder(mixed $value, string $field): bool
    {
        if (! is_string($value)) return false;
        $patterns = [
            // Faker addresses tend to end in random US-style suffixes.
            '/\d{5}-\d{4}$/',                // ZIP+4
            '/(Port|Apt\.|Suite)\s/i',
            // Faker emails tend to contain random surnames + tlds like gulgowski/koss/zulauf.
            '/(gulgowski|zulauf|koss|hilll|kuhic|hodkiewicz|ondricka|harvey)/i',
            // Faker phones often look like +1XXXXXXXXXX (US format).
            '/^\+1\d{10}$/',
            // Generic faker company surnames not matching our brands
            '/(LLC|Inc|Group|and Sons)$/',
        ];
        foreach ($patterns as $p) {
            if (preg_match($p, $value)) return true;
        }
        return false;
    }
}
