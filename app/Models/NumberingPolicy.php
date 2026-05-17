<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class NumberingPolicy extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'document_type', 'prefix', 'suffix', 'separator',
        'year_token', 'sequence_padding', 'reset_policy',
        'scope_dimensions', 'active_from', 'active_to', 'is_active',
    ];

    protected $casts = [
        'sequence_padding' => 'integer',
        'is_active' => 'boolean',
        'scope_dimensions' => 'array',
        'active_from' => 'datetime',
        'active_to' => 'datetime',
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

    /**
     * Generate a format-only preview string. Never reserves a sequence.
     */
    public function preview(int $year): string
    {
        $parts = [];
        if ($this->prefix) {
            $parts[] = $this->prefix;
        }
        $parts[] = str_replace('{YYYY}', (string) $year, $this->year_token);
        $parts[] = str_pad('', $this->sequence_padding, '#');
        if ($this->suffix) {
            $parts[] = $this->suffix;
        }
        return implode($this->separator ?? '', $parts);
    }
}
