<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PdfRender extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'version', 'file_path', 'file_size', 'sha256',
        'page_count', 'paper_size', 'template_used', 'is_current',
        'rendered_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'file_size' => 'integer',
        'page_count' => 'integer',
        'is_current' => 'boolean',
        'rendered_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
