<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id', 'user_id', 'key', 'resource_type', 'resource_id',
        'document_id', 'draft_hash', 'request_hash', 'status',
        'response_data', 'expires_at',
    ];

    protected $casts = [
        'response_data' => 'array',
        'expires_at' => 'datetime',
    ];
}
