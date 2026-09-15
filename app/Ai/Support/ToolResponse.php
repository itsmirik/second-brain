<?php

declare(strict_types=1);

namespace App\Ai\Support;

use JsonException;

/**
 * One JSON encoding for everything the agent's tools hand back, so numbers and
 * Cyrillic text look the same whichever tool produced them. Zero fractions are
 * preserved so a money field never flips between 500000 and 500000.0.
 */
final class ToolResponse
{
    private const FLAGS = JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function json(array $data): string
    {
        try {
            return json_encode($data, self::FLAGS);
        } catch (JsonException) {
            return 'Could not format that result.';
        }
    }
}
