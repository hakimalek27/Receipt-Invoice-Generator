<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id', 'product_id', 'description',
        'section_header', 'image_url',
        'quantity', 'uom', 'unit_price', 'cost_unit',
        'discount', 'line_total',
        'tax_type', 'tax_rate', 'tax_amount',
        'classification_code', 'tax_exemption_reason',
        'sort_order', 'metadata',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'cost_unit' => 'decimal:2',
        'discount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function getMarginAmountAttribute(): ?float
    {
        if ($this->cost_unit === null) {
            return null;
        }
        return round(((float) $this->unit_price - (float) $this->cost_unit) * (float) $this->quantity, 2);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
