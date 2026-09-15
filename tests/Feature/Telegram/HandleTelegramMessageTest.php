<?php

declare(strict_types=1);

namespace Tests\Feature\Telegram;

use App\Actions\Telegram\HandleTelegramMessage;
use App\Ai\Agents\SecondBrainAgent;
use App\Models\ActivityLog;
use App\Models\User;
use App\Telegram\TelegramUpdate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Models\Conversation;
use Tests\TestCase;

class HandleTelegramMessageTest extends TestCase
{
    use RefreshDatabase;

    private const PLACEHOLDER_MESSAGE_ID = 777;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['email' => 'owner@example.test']);

        config([
            'dashboard.owner_email' => $this->owner->email,
            'telegram.bot_token' => 'test-token',
        ]);
    }

    public function test_it_answers_the_owner_and_remembers_the_conversation(): void
    {
        $this->fakeTelegram();
        // Arrange
        SecondBrainAgent::fake(['Записал: садака 500000 сум.']);

        // Act
        app(HandleTelegramMessage::class)($this->update('Отдал 500 000 сум на садака'));

        // Assert
        SecondBrainAgent::assertPrompted('Отдал 500 000 сум на садака');

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'editMessageText')
            && $request['message_id'] === self::PLACEHOLDER_MESSAGE_ID
            && $request['text'] === 'Записал: садака 500000 сум.');

        $this->assertSame(1, ActivityLog::query()->where('direction', 'out')->count());

        $this->assertSame(1, Conversation::query()
            ->where('participant_type', Conversation::participantType($this->owner))
            ->where('participant_id', Conversation::participantKey($this->owner))
            ->count());
    }

    public function test_a_second_message_continues_the_same_conversation(): void
    {
        $this->fakeTelegram();
        SecondBrainAgent::fake(['Записал.', 'Отметил сегодняшней датой.']);

        app(HandleTelegramMessage::class)($this->update('Отдал 500 000 сум на садака', 1));
        app(HandleTelegramMessage::class)($this->update('Да, сегодня', 2));

        SecondBrainAgent::assertPromptedTimes(2);

        $this->assertSame(1, Conversation::query()->count());
    }

    public function test_it_shows_a_placeholder_while_the_agent_thinks(): void
    {
        $this->fakeTelegram();
        // Arrange
        SecondBrainAgent::fake(['Готово.']);

        // Act
        app(HandleTelegramMessage::class)($this->update('Сколько доставок сейчас в пути?'));

        // Assert — the owner sees something immediately, and it is replaced in
        // place by the real answer rather than leaving two messages behind.
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'sendMessage')
            && $request['text'] === HandleTelegramMessage::PLACEHOLDER);

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'sendChatAction')
            && $request['action'] === 'typing');

        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'editMessageText')
            && $request['text'] === 'Готово.');
    }

    public function test_it_falls_back_to_a_new_message_when_the_edit_fails(): void
    {
        // Arrange
        $this->fakeTelegram(editFails: true);
        SecondBrainAgent::fake(['Готово.']);

        // Act
        app(HandleTelegramMessage::class)($this->update('Что там по доставкам?'));

        // Assert — the answer always arrives, even if the loader cannot be edited.
        Http::assertSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'sendMessage')
            && $request['text'] === 'Готово.');
    }

    public function test_it_skips_the_placeholder_for_messages_it_answers_instantly(): void
    {
        $this->fakeTelegram();
        // Arrange — a photo never reaches the agent, so there is nothing to wait for.
        $update = TelegramUpdate::fromArray([
            'update_id' => 7,
            'message' => [
                'chat' => ['id' => 424242],
                'photo' => [['file_id' => 'abc']],
            ],
        ]);

        // Act
        app(HandleTelegramMessage::class)($update);

        // Assert
        Http::assertNotSent(fn (HttpRequest $request): bool => str_contains($request->url(), 'editMessageText'));
        Http::assertSentCount(1);
    }

    /**
     * Http::fake() merges stubs and the first match wins, so each test fakes
     * Telegram itself — that keeps one test free to describe a failing edit.
     */
    private function fakeTelegram(bool $editFails = false): void
    {
        Http::fake([
            '*/sendMessage' => Http::response([
                'ok' => true,
                'result' => ['message_id' => self::PLACEHOLDER_MESSAGE_ID],
            ]),
            '*/editMessageText' => $editFails
                ? Http::response(['ok' => false, 'description' => 'message is not modified'], 400)
                : Http::response(['ok' => true, 'result' => []]),
            '*' => Http::response(['ok' => true, 'result' => true]),
        ]);
    }

    private function update(string $text, int $updateId = 1): TelegramUpdate
    {
        return TelegramUpdate::fromArray([
            'update_id' => $updateId,
            'message' => [
                'chat' => ['id' => 424242],
                'text' => $text,
            ],
        ]);
    }
}
