<?php

declare(strict_types=1);

namespace App\Ai\Support;

use App\Models\House;
use Illuminate\Database\Eloquent\Collection;

/**
 * How the agent's tools find the owner's houses. The model names a house the
 * way the owner said it — "Чиланзар" for «Дом на Чиланзаре» — so an exact
 * name (ignoring case) wins, then the one house whose name contains it.
 * Anything vaguer matches nothing: guessing the wrong house would misfile
 * real money.
 */
final class HouseLookup
{
    /**
     * @return Collection<int, House>
     */
    public function all(?int $ownerId): Collection
    {
        return $ownerId === null ? new Collection : House::ownedBy($ownerId);
    }

    public function find(int $ownerId, string $name): ?House
    {
        $exact = House::named($ownerId, $name);

        if ($exact !== null) {
            return $exact;
        }

        $needle = mb_strtolower(House::normalizeName($name));

        if ($needle === '') {
            return null;
        }

        $partial = $this->all($ownerId)->filter(
            static fn (House $house): bool => str_contains(mb_strtolower($house->name), $needle),
        );

        return $partial->count() === 1 ? $partial->first() : null;
    }

    /**
     * The owner's house when they keep exactly one — the obvious home for an
     * entry nobody named a house for. Null with none or several.
     */
    public function only(int $ownerId): ?House
    {
        $all = $this->all($ownerId);

        return $all->count() === 1 ? $all->first() : null;
    }

    /**
     * The owner's houses as «A», «B» — for tool descriptions and replies.
     */
    public function describe(?int $ownerId): string
    {
        $names = $this->all($ownerId)->map(static fn (House $house): string => '«'.$house->name.'»');

        return $names->isEmpty() ? 'none yet' : $names->implode(', ');
    }
}
