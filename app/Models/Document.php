<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_VOID = 'void';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CONVERTED = 'converted';

    protected $fillable = [
        'company_id', 'document_type', 'status', 'official_number',
        'customer_id', 'document_date', 'due_date',
        'subtotal', 'discount_total', 'tax_total', 'grand_total',
        'currency', 'fx_rate', 'notes', 'terms', 'internal_notes',
        'product_line', 'include_arabic_salutation',
        'show_computer_generated_footer',
        'show_amount_in_words', 'amount_in_words_locale',
        'amount_in_words_currency', 'amount_in_words_text',
        'template_version_id', 'converted_from_id', 'draft_hash',
        'issuer_snapshot_json', 'buyer_snapshot_json',
        'bank_snapshot_json', 'terms_snapshot_json',
        'tax_snapshot_json', 'currency_fx_snapshot_json',
        'issue_timezone_snapshot', 'issued_at', 'voided_at',
        'void_reason', 'voided_by',
        'myinvois_status', 'myinvois_uuid',
        'myinvois_submission_uid', 'myinvois_validated_at',
    ];

    protected $casts = [
        'document_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'fx_rate' => 'decimal:8',
        'show_amount_in_words' => 'boolean',
        'include_arabic_salutation' => 'boolean',
        'show_computer_generated_footer' => 'boolean',
        'issuer_snapshot_json' => 'array',
        'buyer_snapshot_json' => 'array',
        'bank_snapshot_json' => 'array',
        'terms_snapshot_json' => 'array',
        'tax_snapshot_json' => 'array',
        'currency_fx_snapshot_json' => 'array',
        'issued_at' => 'datetime',
        'voided_at' => 'datetime',
        'myinvois_validated_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DocumentItem::class)->orderBy('sort_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DocumentStatusHistory::class)->orderBy('created_at');
    }

    public function pdfRenders(): HasMany
    {
        return $this->hasMany(PdfRender::class);
    }

    public function paymentAllocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentAttachment::class)->orderBy('sort_order');
    }

    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'converted_from_id');
    }

    public function convertedTo(): HasMany
    {
        return $this->hasMany(Document::class, 'converted_from_id');
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeIssued($query)
    {
        return $query->where('status', self::STATUS_ISSUED);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    public function recomputeTotals(): void
    {
        $this->subtotal = $this->items->sum(
            fn (DocumentItem $item) => round((float) $item->quantity * (float) $item->unit_price, 2)
        );
        $this->discount_total = $this->items->sum('discount');
        $this->tax_total = $this->items->sum('tax_amount');
        $this->grand_total = $this->subtotal - $this->discount_total + $this->tax_total;
    }
}
