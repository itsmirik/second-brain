<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table): void {
            $table->id();

            // 'in'  = inbound Telegram update we received
            // 'out' = outbound message/action we performed
            $table->string('direction', 3);

            // Telegram's monotonic update_id. Unique so a retried webhook delivery
            // cannot be processed twice (idempotency). Null for outbound rows;
            // both MySQL and SQLite allow multiple NULLs under a unique index.
            $table->unsignedBigInteger('telegram_update_id')->nullable()->unique();

            // Stored as string: chat ids are large and we never do math on them.
            $table->string('chat_id')->nullable()->index();

            // text | photo | document | callback | rejected | reply | error | ...
            $table->string('message_type')->nullable();

            // Short, non-sensitive summary of what happened (never raw secrets).
            $table->text('payload_summary')->nullable();

            $table->timestamps();

            $table->index(['direction', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
