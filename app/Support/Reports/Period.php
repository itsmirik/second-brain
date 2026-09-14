<?php

declare(strict_types=1);

namespace App\Support\Reports;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * A reporting window (day / week / month / quarter / year) anchored on a date.
 *
 * Turns a period type + anchor date into a concrete [from, to] range, a human
 * label, and the anchors for the previous/next window so the UI can page
 * through time without doing date maths itself.
 */
final class Period
{
    public const TYPES = ['day', 'week', 'month', 'quarter', 'year'];

    private function __construct(
        public readonly string $type,
        public readonly CarbonImmutable $anchor,
    ) {}

    public static function make(?string $type, ?string $date): self
    {
        $type = $type ?? 'month';

        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Unknown period type [{$type}].");
        }

        $anchor = $date !== null && $date !== ''
            ? CarbonImmutable::parse($date)
            : CarbonImmutable::now();

        return new self($type, $anchor->startOfDay());
    }

    public function from(): CarbonImmutable
    {
        return match ($this->type) {
            'day' => $this->anchor->startOfDay(),
            'week' => $this->anchor->startOfWeek(),
            'month' => $this->anchor->startOfMonth(),
            'quarter' => $this->anchor->startOfQuarter(),
            'year' => $this->anchor->startOfYear(),
            default => throw $this->unknownType(),
        };
    }

    public function to(): CarbonImmutable
    {
        return match ($this->type) {
            'day' => $this->anchor->endOfDay(),
            'week' => $this->anchor->endOfWeek(),
            'month' => $this->anchor->endOfMonth(),
            'quarter' => $this->anchor->endOfQuarter(),
            'year' => $this->anchor->endOfYear(),
            default => throw $this->unknownType(),
        };
    }

    public function fromDate(): string
    {
        return $this->from()->toDateString();
    }

    public function toDate(): string
    {
        return $this->to()->toDateString();
    }

    public function label(): string
    {
        return match ($this->type) {
            'day' => $this->from()->translatedFormat('D, d M Y'),
            'week' => $this->from()->translatedFormat('d M').' – '.$this->to()->translatedFormat('d M Y'),
            'month' => $this->from()->translatedFormat('F Y'),
            'quarter' => $this->from()->quarter.' кв. '.$this->from()->year,
            'year' => $this->from()->year.' г.',
            default => throw $this->unknownType(),
        };
    }

    /** Anchor date (Y-m-d) for the previous window of the same type. */
    public function previousAnchor(): string
    {
        return $this->shift(-1)->toDateString();
    }

    /** Anchor date (Y-m-d) for the next window of the same type. */
    public function nextAnchor(): string
    {
        return $this->shift(1)->toDateString();
    }

    /** Whether the next window lies wholly in the future (nothing to show). */
    public function isCurrentOrLatest(): bool
    {
        return $this->to() >= CarbonImmutable::now();
    }

    private function shift(int $direction): CarbonImmutable
    {
        return match ($this->type) {
            'day' => $this->anchor->addDays($direction),
            'week' => $this->anchor->addWeeks($direction),
            'month' => $this->anchor->addMonthsNoOverflow($direction),
            'quarter' => $this->anchor->addQuartersNoOverflow($direction),
            'year' => $this->anchor->addYearsNoOverflow($direction),
            default => throw $this->unknownType(),
        };
    }

    private function unknownType(): InvalidArgumentException
    {
        return new InvalidArgumentException("Unknown period type [{$this->type}].");
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label(),
            'from' => $this->fromDate(),
            'to' => $this->toDate(),
            'anchor' => $this->anchor->toDateString(),
            'previous' => $this->previousAnchor(),
            'next' => $this->nextAnchor(),
        ];
    }
}
