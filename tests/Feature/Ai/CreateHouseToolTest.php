<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Tools\CreateHouseTool;
use App\Models\House;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class CreateHouseToolTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config(['dashboard.owner_email' => $this->owner->email]);
    }

    public function test_it_adds_a_house_for_the_owner(): void
    {
        // Act
        $result = $this->create(['name' => '  Юнусабад ']);

        // Assert
        $house = House::query()->sole();
        $this->assertSame('Юнусабад', $house->name);
        $this->assertSame($this->owner->id, $house->user_id);
        $this->assertStringContainsString('«Юнусабад»', $result);
    }

    public function test_it_does_not_duplicate_an_existing_house(): void
    {
        House::factory()->for($this->owner)->create(['name' => 'Юнусабад']);

        $result = $this->create(['name' => 'юнусабад']);

        $this->assertSame(1, House::query()->count());
        $this->assertStringContainsString('already', $result);
    }

    public function test_it_needs_a_name(): void
    {
        $result = $this->create(['name' => '   ']);

        $this->assertSame(0, House::query()->count());
        $this->assertStringContainsString('name', $result);
    }

    public function test_it_rejects_a_name_that_is_too_long(): void
    {
        $this->create(['name' => str_repeat('д', House::NAME_MAX + 1)]);

        $this->assertSame(0, House::query()->count());
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function create(array $arguments): string
    {
        return (string) app(CreateHouseTool::class)->handle(new Request($arguments));
    }
}
