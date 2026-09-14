<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single per-user setting (see the settings migration). Values are stored as
 * text; callers cast as needed.
 *
 * @property int $id
 * @property int $user_id
 * @property string $key
 * @property string|null $value
 */
class Setting extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function get(int $userId, string $key, ?string $default = null): ?string
    {
        return self::query()
            ->where('user_id', $userId)
            ->where('key', $key)
            ->value('value') ?? $default;
    }

    public static function put(int $userId, string $key, ?string $value): void
    {
        self::query()->updateOrCreate(
            ['user_id' => $userId, 'key' => $key],
            ['value' => $value],
        );
    }
}
