<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelegramMessage extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'document_id',
        'chat_id',
        'telegram_user_id',
        'direction',
        'payload_redacted',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'payload_redacted' => 'array',
        'sent_at' => 'datetime',
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
