<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Agents\SecondBrainAgent;
use App\Ai\Tools\AtheerReportsTool;
use App\Ai\Tools\CharityStatusTool;
use App\Ai\Tools\LogEntryTool;
use App\Ai\Tools\MoneyReportTool;
use App\Ai\Tools\SearchEntriesTool;
use App\Ai\Tools\UpdateEntryTool;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\ObjectSchema;
use Tests\TestCase;

class AgentToolsTest extends TestCase
{
    public function test_the_brain_can_read_write_and_correct(): void
    {
        // Arrange + Act
        $tools = collect(SecondBrainAgent::make()->tools())
            ->map(static fn (Tool $tool): string => $tool::class)
            ->all();

        // Assert — reading is as essential as writing: without the read tools
        // the bot tells the owner to go look at the dashboard themselves.
        $this->assertEqualsCanonicalizing([
            AtheerReportsTool::class,
            SearchEntriesTool::class,
            MoneyReportTool::class,
            CharityStatusTool::class,
            LogEntryTool::class,
            UpdateEntryTool::class,
        ], $tools);
    }

    public function test_every_tool_compiles_a_schema_a_provider_can_accept(): void
    {
        foreach (SecondBrainAgent::make()->tools() as $tool) {
            $schema = (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory)))->toArray();

            $this->assertSame('object', $schema['type'], $tool::class);
            $this->assertNotEmpty($schema['properties'], $tool::class);
            $this->assertNotEmpty(trim($tool->description()), $tool::class);
        }
    }

    public function test_the_prompt_tells_the_model_today_and_that_it_can_look_things_up(): void
    {
        $instructions = (string) SecondBrainAgent::make()->instructions();

        $this->assertStringContainsString(now()->toDateString(), $instructions);
        $this->assertStringContainsString('READ everything that was ever logged', $instructions);
    }
}
