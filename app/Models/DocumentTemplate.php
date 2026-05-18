<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'document_type', 'paper_size', 'is_default',
        'show_amount_in_words', 'amount_in_words_locale',
        'amount_in_words_currency', 'amount_in_words_zero_sen_style',
        'amount_in_words_label', 'amount_in_words_position',
        'is_active', 'layout_config', 'metadata',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'show_amount_in_words' => 'boolean',
        'is_active' => 'boolean',
        'layout_config' => 'array',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }
}
