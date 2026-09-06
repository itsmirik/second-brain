<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single log entry in one of the owner's life sections (Personal, Health,
 * Budget, ...). Deliberately generic: free-text body, an optional money amount
 * (used by finance-flavoured sections), a date, and free-form tags.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('section')->index();
            $table->text('body');
            $table->decimal('amount', 15, 2)->nullable();
            $table->date('occurred_at')->index();
            $table->json('tags')->nullable();
            $table->timestamps();

            $table->index(['section', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
