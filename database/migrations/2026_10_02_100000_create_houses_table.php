<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The owner's houses. The home business runs several houses at once, each its
 * own project, so its entries are split by house (see entries.house_id).
 * Names are unique per owner; case-insensitive uniqueness is enforced in the
 * app, since SQLite compares Cyrillic case-sensitively.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('houses', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(User::class)->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('houses');
    }
};
