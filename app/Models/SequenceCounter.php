<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SequenceCounter extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'document_type', 'year', 'current_sequence',
    ];

    protected $casts = [
        'year' => 'integer',
        'current_sequence' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForType($query, string $documentType)
    {
        return $query->where('document_type', $documentType);
    }

    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }
}
