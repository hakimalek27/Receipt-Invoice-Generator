<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'document_id', 'original_name', 'storage_path',
        'mime_type', 'size_bytes', 'caption', 'sort_order',
        'include_in_pdf', 'metadata',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'sort_order' => 'integer',
        'include_in_pdf' => 'boolean',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
