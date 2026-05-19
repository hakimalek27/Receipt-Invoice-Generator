<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'address',
        'address_line_2',
        'city',
        'state',
        'postcode',
        'country',
        'phone',
        'email',
        'registration_number',
        'tin',
        'sst_registration_number',
        'msic_code',
        'business_activity_description',
        'is_active',
        'settings',
        'logo_path',
        'stamp_path',
        'signature_path',
        'brand_primary',
        'brand_secondary',
        'brand_accent',
        'pdf_boilerplate',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
        'pdf_boilerplate' => 'array',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        return $this->assetUrl($this->logo_path);
    }

    public function getStampUrlAttribute(): ?string
    {
        return $this->assetUrl($this->stamp_path);
    }

    public function getSignatureUrlAttribute(): ?string
    {
        return $this->assetUrl($this->signature_path);
    }

    public function getBrandPaletteAttribute(): array
    {
        return [
            'primary' => $this->brand_primary ?: '#1a3a5c',
            'secondary' => $this->brand_secondary ?: '#f0f4f8',
            'accent' => $this->brand_accent ?: '#16427a',
        ];
    }

    private function assetUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($path) ? $disk->url($path) : null;
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(CompanyBankAccount::class)->orderBy('sort_order');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Snapshot of profile completeness — drives the dashboard banner that
     * nudges admins to finish setting up before generating PDFs.
     *
     * Returns ['missing' => [...labelled gaps...], 'tab' => deep-link target].
     */
    public function onboardingChecklist(): array
    {
        $missing = [];

        if (empty($this->phone) || preg_match('/^\+1\d{10}$/', (string) $this->phone)) {
            $missing[] = ['label' => 'Phone number', 'tab' => 'company'];
        }
        if (empty($this->email) || preg_match('/(gulgowski|zulauf|koss|kuhic)/i', (string) $this->email)) {
            $missing[] = ['label' => 'Company email', 'tab' => 'company'];
        }
        if (empty($this->address_line_2)) {
            $missing[] = ['label' => 'Full address (line 2)', 'tab' => 'company'];
        }
        if (empty($this->logo_path)) {
            $missing[] = ['label' => 'Company logo', 'tab' => 'branding'];
        }
        if (empty($this->stamp_path) || empty($this->signature_path)) {
            $missing[] = ['label' => 'Stamp + signature image', 'tab' => 'branding'];
        }
        if ($this->bankAccounts()->count() === 0) {
            $missing[] = ['label' => 'At least one bank account', 'tab' => 'bank-accounts'];
        }

        return [
            'complete' => count($missing) === 0,
            'missing' => $missing,
            'first_tab' => $missing[0]['tab'] ?? null,
        ];
    }
}
