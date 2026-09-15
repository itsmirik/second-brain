<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Entry>
 */
class EntryFactory extends Factory
{
    /** @var class-string<Entry> */
    protected $model = Entry::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'section' => 'personal',
            'body' => fake()->sentence(),
            'amount' => null,
            'occurred_at' => Date::today()->toDateString(),
            'tags' => null,
        ];
    }
}
