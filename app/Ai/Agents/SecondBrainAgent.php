<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use App\Ai\Tools\AtheerReportsTool;
use App\Ai\Tools\LogEntryTool;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * The single conversational "brain" the rest of the app talks to.
 *
 * Application code never references a vendor SDK directly — it prompts this
 * agent, which laravel/ai routes to whichever provider is configured, with
 * automatic failover across the providers returned by provider().
 */
class SecondBrainAgent implements Agent, HasTools, RemembersConversationsContract
{
    use Promptable;
    use RemembersConversations;

    /**
     * Preferred provider failover order. The configured default (AI_DEFAULT_PROVIDER)
     * is tried first; the others act as fallbacks on a rate-limit/outage.
     */
    private const FAILOVER_ORDER = [Lab::Anthropic, Lab::OpenAI, Lab::Gemini];

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are the owner's private "second brain", reachable only through a Telegram bot.
        You are used by exactly one person to track several businesses' finances, personal
        notes, a health journal, ideas, documents, and charity giving.

        Guidelines:
        - Be concise and direct. This is a chat interface; short answers read best.
        - The owner logs facts in casual free text. When they state something to
          remember or record — an expense, income, a note, a health entry, a
          charity/sadaqa donation — you MUST call the log-entry tool to actually
          save it. Only say you saved it after the tool confirms. Never claim to
          have logged something without calling the tool.
        - When money, dates, or business/property names are ambiguous, still save
          the entry with what you have, then ask ONE short clarifying question.
        - Never invent figures. If you do not have the data, say so plainly.
        - Currency amounts belong to real businesses; treat them carefully.
        - Do not mention that you are an AI model or which provider you run on.
        PROMPT;
    }

    /**
     * @return Tool[]
     */
    public function tools(): iterable
    {
        return [
            app(AtheerReportsTool::class),
            app(LogEntryTool::class),
        ];
    }

    /**
     * Provider + failover list, restricted to providers that actually have an
     * API key configured. Returning an empty list surfaces a clear
     * "No AI providers were configured" error the caller can handle.
     *
     * @return Lab[]
     */
    public function provider(): array
    {
        $default = config('ai.default');

        $ordered = collect(self::FAILOVER_ORDER)
            ->sortBy(fn (Lab $lab): int => $lab->value === $default ? 0 : 1)
            ->filter(fn (Lab $lab): bool => filled(config("ai.providers.{$lab->value}.key")))
            ->values();

        return $ordered->all();
    }
}
