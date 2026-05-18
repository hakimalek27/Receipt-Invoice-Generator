<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings' => 'array',
    ];

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
