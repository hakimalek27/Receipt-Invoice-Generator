<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramConfirmationToken extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'document_id',
        'chat_id',
        'telegram_user_id',
        'token_hash',
        'draft_hash',
        'idempotency_key',
        'expires_at',
        'used_at',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
