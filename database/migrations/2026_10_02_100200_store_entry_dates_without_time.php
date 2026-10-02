<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * entries.occurred_at holds a date, but Eloquent's "date" cast wrote it as
 * "Y-m-d 00:00:00". SQLite keeps that as text, so a range filter ending on
 * that day compared "2026-10-02 00:00:00" with "2026-10-02" and left the day
 * out — the last day of every report, month and "today" went missing. Entry
 * now stores a plain date; this trims the rows written before that.
 *
 * Only the format changes, never the date. On MySQL the column is a real
 * DATE, already ten characters long, so nothing matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('entries')
            ->whereRaw('length(occurred_at) > 10')
            ->update(['occurred_at' => DB::raw('substr(occurred_at, 1, 10)')]);
    }

    public function down(): void
    {
        // Nothing to undo: a plain date is what the column always meant.
    }
};
