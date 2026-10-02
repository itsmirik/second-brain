<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\EntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;

/**
 * A log entry in a life section (see config/dashboard.php). Generic by design —
 * body is always present; amount/tags are optional structure on top.
 *
 * @property int $id
 * @property int $user_id
 * @property string $section
 * @property int|null $house_id
 * @property string $body
 * @property string|null $amount
 * @property Carbon $occurred_at
 * @property array<int, string>|null $tags
 */
class Entry extends Model
{
    /** @use HasFactory<EntryFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'section',
        'house_id',
        'body',
        'amount',
        'occurred_at',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'date',
            'amount' => 'decimal:2',
            'tags' => 'array',
        ];
    }

    /**
     * Stored as a plain date. The "date" cast alone writes "Y-m-d 00:00:00",
     * which SQLite keeps as text, so a range ending on that day compared
     * "2026-10-02 00:00:00" with "2026-10-02" and silently lost its last day.
     *
     * @return Attribute<never, string>
     */
    protected function occurredAt(): Attribute
    {
        return Attribute::make(
            set: static fn (mixed $value): string => Date::parse($value)->toDateString(),
        );
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<House, $this> */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
    }

    /** @param  Builder<Entry>  $query */
    public function scopeForSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }

    /**
     * One house's entries, or — with null — the entries filed under no house.
     *
     * @param  Builder<Entry>  $query
     */
    public function scopeForHouse(Builder $query, ?int $houseId): void
    {
        if ($houseId === null) {
            $query->whereNull('house_id');

            return;
        }

        $query->where('house_id', $houseId);
    }

    /** @param  Builder<Entry>  $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /** @param  Builder<Entry>  $query */
    public function scopeOccurredBetween(Builder $query, string $from, string $to): void
    {
        $query->whereBetween('occurred_at', [$from, $to]);
    }
}
