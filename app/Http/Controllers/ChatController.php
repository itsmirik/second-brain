<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ai\Agents\SecondBrainAgent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Models\ConversationMessage;
use Throwable;

/**
 * Web chat with the second brain — the same agent the Telegram bot uses, in
 * the browser. Conversations are persisted (laravel/ai) so history survives
 * reloads; the owner is the conversation participant.
 */
class ChatController extends Controller
{
    /** Roles that represent visible chat turns (skip tool-only rows). */
    private const VISIBLE_ROLES = ['user', 'assistant'];

    public function index(Request $request): Response
    {
        $user = $request->user();

        $conversation = Conversation::query()
            ->where('participant_type', Conversation::participantType($user))
            ->where('participant_id', Conversation::participantKey($user))
            ->latest('updated_at')
            ->first();

        return Inertia::render('Chat', [
            'conversationId' => $conversation?->id,
            'history' => $conversation ? $this->transcript($conversation->id) : [],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $agent = SecondBrainAgent::make();

        $conversationId = $validated['conversation_id'] ?? null;
        $conversationId !== null
            ? $agent->continue($conversationId, $user)
            : $agent->forUser($user);

        try {
            $reply = $agent->prompt($validated['message'])->text;
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => 'The assistant is temporarily unavailable. Please try again shortly.',
            ], 503);
        }

        return response()->json([
            'conversation_id' => $agent->currentConversation(),
            'reply' => $reply,
        ]);
    }

    /**
     * Flatten a stored conversation into visible {role, content} turns.
     *
     * @return list<array{role:string,content:string}>
     */
    private function transcript(string $conversationId): array
    {
        $rows = ConversationMessage::query()
            ->where('conversation_id', $conversationId)
            ->whereIn('role', self::VISIBLE_ROLES)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['role', 'content']);

        $turns = [];

        foreach ($rows as $message) {
            $content = trim((string) $message->getAttribute('content'));

            if ($content === '') {
                continue;
            }

            $turns[] = [
                'role' => (string) $message->getAttribute('role'),
                'content' => $content,
            ];
        }

        return $turns;
    }
}
