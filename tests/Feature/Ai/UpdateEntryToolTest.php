<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\UpdateEntryTool;
use App\Models\Entry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class UpdateEntryToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config(['dashboard.owner_email' => $this->owner->email]);
    }

    public function test_it_updates_body_amount_and_date(): void
    {
        // Arrange
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Продукты',
            'amount' => '-80000',
            'occurred_at' => '2026-09-05',
        ]);

        // Act
        $result = $this->update([
            'id' => $entry->id,
            'body' => 'Продукты и бытовая химия',
            'amount' => -95000,
            'occurred_at' => '2026-09-06',
        ]);

        // Assert
        $entry->refresh();
        $this->assertSame('Продукты и бытовая химия', $entry->body);
        $this->assertSame(-95000.0, (float) $entry->amount);
        $this->assertSame('2026-09-06', $entry->occurred_at->toDateString());
        $this->assertStringContainsString('Updated', $result);
    }

    public function test_it_moves_an_entry_to_another_section_and_drops_amount_for_non_money_sections(): void
    {
        $entry = Entry::factory()->for($this->owner)->create([
            'section' => 'budget',
            'body' => 'Записал не туда',
            'amount' => '-50000',
            'occurred_at' => '2026-09-05',
        ]);

        $this->update(['id' => $entry->id, 'section' => 'personal']);

        $entry->refresh();
        $this->assertSame('personal', $entry->section);
        $this->assertNull($entry->amount);
    }

    public function test_it_refuses_to_touch_another_users_entry(): void
    {
        $stranger = User::factory()->create();

        $entry = Entry::factory()->for($stranger)->create([
            'section' => 'budget',
            'body' => 'Чужая запись',
            'amount' => '999',
        ]);

        $result = $this->update(['id' => $entry->id, 'body' => 'взлом']);

        $entry->refresh();
        $this->assertSame('Чужая запись', $entry->body);
        $this->assertStringContainsString('No entry', $result);
    }

    public function test_it_rejects_an_unknown_section(): void
    {
        $entry = Entry::factory()->for($this->owner)->create(['section' => 'personal']);

        $result = $this->update(['id' => $entry->id, 'section' => 'crypto']);

        $entry->refresh();
        $this->assertSame('personal', $entry->section);
        $this->assertStringContainsString('crypto', $result);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function update(array $arguments): string
    {
        return app(UpdateEntryTool::class)->handle(new Request($arguments));
    }
}
