<?php

declare(strict_types=1);

namespace App\Support\Dashboard;

/**
 * Thin accessor over the config('dashboard.sections') list.
 *
 * @phpstan-type SectionConfig array{key:string,label:string,description:string,icon:string,driver:string,money:bool,status:string}
 */
final class Sections
{
    /** @return list<SectionConfig> */
    public static function all(): array
    {
        /** @var list<SectionConfig> */
        return array_values(config('dashboard.sections', []));
    }

    /** @return SectionConfig|null */
    public static function find(string $key): ?array
    {
        foreach (self::all() as $section) {
            if ($section['key'] === $key) {
                return $section;
            }
        }

        return null;
    }

    /**
     * A live, entries-backed section by key, or null if it isn't one.
     *
     * @return SectionConfig|null
     */
    public static function entrySection(string $key): ?array
    {
        $section = self::find($key);

        if ($section === null || $section['status'] !== 'live' || $section['driver'] !== 'entries') {
            return null;
        }

        return $section;
    }

    /**
     * Keys of every live, entries-backed section (used for route registration).
     *
     * @return list<string>
     */
    public static function entryKeys(): array
    {
        $keys = [];

        foreach (self::all() as $section) {
            if ($section['status'] === 'live' && $section['driver'] === 'entries') {
                $keys[] = $section['key'];
            }
        }

        return $keys;
    }
}
