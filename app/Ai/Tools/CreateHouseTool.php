<?php

declare(strict_types=1);

namespace App\Ai\Tools;

use App\Ai\Support\HouseLookup;
use App\Ai\Support\OwnerResolver;
use App\Models\House;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Throwable;

/**
 * Adds a house to the owner's home business, so "I started a house in
 * Yunusabad, paid 300 million for the plot" can be filed in one go instead of
 * sending the owner to the dashboard to create the house first.
 */
final class CreateHouseTool implements Tool
{
    public function __construct(
        private readonly OwnerResolver $owner,
        private readonly HouseLookup $houses,
    ) {}

    public function description(): string
    {
        $houses = $this->houses->describe($this->owner->id());

        return <<<TEXT
        Add a new house to the owner's home business. Each house is its own
        project, and home-business entries are filed under a house.

        Only call this when the owner says they started, bought or took on a
        new house, or asks you to add one. Never add a house just because a
        name in a message did not match an existing one — ask which house they
        meant instead. Existing houses: {$houses}.

        Afterwards, log the owner's entries for it under the same name.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()
                ->description('Short name the owner uses for the house, e.g. "Юнусабад" or "Дом на Чиланзаре".')
                ->required(),
        ];
    }

    public function handle(Request $request): string
    {
        $ownerId = $this->owner->id();

        if ($ownerId === null) {
            return 'Could not add the house: no owner account is configured.';
        }

        $raw = $request['name'] ?? null;
        $name = House::normalizeName(is_string($raw) ? $raw : '');

        if ($name === '') {
            return 'Could not add the house: it needs a name.';
        }

        if (mb_strlen($name) > House::NAME_MAX) {
            return 'Could not add the house: keep the name under '.House::NAME_MAX.' characters.';
        }

        $existing = House::named($ownerId, $name);

        if ($existing !== null) {
            return "House «{$existing->name}» already exists — file its entries under it.";
        }

        try {
            $house = House::query()->create(['user_id' => $ownerId, 'name' => $name]);
        } catch (Throwable $e) {
            report($e);

            return 'Could not add the house right now. Please try again shortly.';
        }

        return "Added house «{$house->name}». It is on the dashboard; log its entries with house \"{$house->name}\".";
    }
}
