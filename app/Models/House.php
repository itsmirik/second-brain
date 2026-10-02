<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HouseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the owner's houses. The home business runs several at once, each its
 * own project, so their money is kept apart: entries in a section split by
 * house (config/dashboard.php, "houses") carry a house_id.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 */
class House extends Model
{
    /** @use HasFactory<HouseFactory> */
    use HasFactory;

    public const NAME_MAX = 100;

    protected $fillable = [
        'user_id',
        'name',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Entry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }

    /** @param  Builder<House>  $query */
    public function scopeForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId);
    }

    /**
     * The owner's houses, oldest first — a stable order, so a new house lands
     * at the end of the switcher.
     *
     * @return Collection<int, House>
     */
    public static function ownedBy(int $userId): Collection
    {
        return self::query()->forUser($userId)->orderBy('id')->get();
    }

    /**
     * Trim and collapse inner whitespace, so "  Дом  1 " and "Дом 1" are one name.
     */
    public static function normalizeName(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * The owner's house with exactly this name, ignoring case. Compared in PHP
     * because SQLite's lower() only folds ASCII and these names are Cyrillic.
     */
    public static function named(int $userId, string $name): ?self
    {
        $needle = mb_strtolower(self::normalizeName($name));

        return self::ownedBy($userId)->first(
            static fn (House $house): bool => mb_strtolower($house->name) === $needle,
        );
    }
}
