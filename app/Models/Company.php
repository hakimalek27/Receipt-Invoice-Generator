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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
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
}
