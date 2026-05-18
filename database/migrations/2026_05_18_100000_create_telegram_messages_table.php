<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chat_id', 64)->nullable();
            $table->string('telegram_user_id', 64)->nullable();
            $table->string('direction', 20);
            $table->jsonb('payload_redacted')->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['chat_id', 'direction']);
            $table->index(['document_id', 'direction']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
