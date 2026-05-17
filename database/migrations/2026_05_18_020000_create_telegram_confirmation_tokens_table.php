<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_confirmation_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('chat_id', 64);
            $table->string('telegram_user_id', 64)->nullable();
            $table->string('token_hash', 64)->unique();
            $table->string('draft_hash', 64);
            $table->string('idempotency_key', 120);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'telegram_user_id']);
            $table->index(['document_id', 'used_at']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_confirmation_tokens');
    }
};
