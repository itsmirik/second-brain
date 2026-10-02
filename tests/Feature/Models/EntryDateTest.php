<?php

declare(strict_types=1);

namespace Tests\Feature\Models;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * occurred_at is a plain date. Stored with a time ("2026-10-02 00:00:00"),
 * SQLite compares it as text, and every range filter drops its last day.
 */
class EntryDateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_date_is_stored_without_a_time(): void
    {
        // Act
        $entry = Entry::factory()->create(['occurred_at' => '2026-10-02']);

        // Assert
        $this->assertSame('2026-10-02', DB::table('entries')->where('id', $entry->id)->value('occurred_at'));
        $this->assertSame('2026-10-02', $entry->refresh()->occurred_at->toDateString());
    }

    public function test_the_migration_trims_dates_stored_with_a_time(): void
    {
        // Arrange — a row as the old code wrote it.
        $user = User::factory()->create();
        DB::table('entries')->insert([
            'user_id' => $user->id,
            'section' => 'budget',
            'body' => 'Старая запись',
            'amount' => -1000,
            'occurred_at' => '2026-09-30 00:00:00',
        ]);

        // Act
        $migration = require database_path('migrations/2026_10_02_100200_store_entry_dates_without_time.php');
        $migration->up();

        // Assert
        $this->assertSame('2026-09-30', DB::table('entries')->value('occurred_at'));
    }
}
