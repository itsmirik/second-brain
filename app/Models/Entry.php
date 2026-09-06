<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A log entry in a life section (see config/dashboard.php). Generic by design —
 * body is always present; amount/tags are optional structure on top.
 *
 * @property int $id
 * @property int $user_id
 * @property string $section
 * @property string $body
 * @property string|null $amount
 * @property Carbon $occurred_at
 * @property array<int, string>|null $tags
 */
class Entry extends Model
{
    protected $fillable = [
        'user_id',
        'section',
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param  Builder<Entry>  $query */
    public function scopeForSection(Builder $query, string $section): void
    {
        $query->where('section', $section);
    }
}
