<?php

declare(strict_types=1);

use App\Models\House;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which house a home-business entry belongs to. Nullable: entries logged
 * before houses existed, and entries whose house is still unknown, stay in the
 * section without one. Deleting a house keeps its entries — they lose only
 * the house, never the money.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->foreignIdFor(House::class)
                ->nullable()
                ->after('section')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table): void {
            $table->dropConstrainedForeignIdFor(House::class);
        });
    }
};
