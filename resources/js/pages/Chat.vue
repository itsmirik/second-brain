<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import type { ChatTurn } from '@/types';
import { Head } from '@inertiajs/vue3';
import { nextTick, onMounted, ref } from 'vue';

const props = defineProps<{
    conversationId: string | null;
    history: ChatTurn[];
}>();

const messages = ref<ChatTurn[]>([...props.history]);
const conversationId = ref<string | null>(props.conversationId);
const draft = ref('');
const sending = ref(false);
const error = ref<string | null>(null);
const scroller = ref<HTMLElement | null>(null);

function readCookie(name: string): string {
    const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
    return match ? decodeURIComponent(match[1]) : '';
}

async function scrollToBottom() {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
}

onMounted(scrollToBottom);

async function send() {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    error.value = null;
    messages.value.push({ role: 'user', content: text });
    draft.value = '';
    sending.value = true;
    await scrollToBottom();

    try {
        const res = await fetch('/chat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': readCookie('XSRF-TOKEN'),
            },
            body: JSON.stringify({
                message: text,
                conversation_id: conversationId.value,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            error.value = data.error ?? 'Что-то пошло не так. Попробуй ещё раз.';
        } else {
            conversationId.value = data.conversation_id ?? conversationId.value;
            messages.value.push({ role: 'assistant', content: data.reply });
        }
    } catch {
        error.value = 'Ошибка сети. Попробуй ещё раз.';
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
}
</script>

<template>
    <Head title="Чат" />

    <AppLayout title="Чат">
        <div class="flex h-[calc(100vh-8rem)] flex-col rounded-xl border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
            <!-- Transcript -->
            <div ref="scroller" class="flex-1 space-y-4 overflow-y-auto p-4 sm:p-6">
                <div
                    v-if="!messages.length"
                    class="flex h-full items-center justify-center text-center text-sm text-neutral-400"
                >
                    Спроси про бизнес, финансы или что угодно, что ты записываешь.
                </div>

                <div
                    v-for="(turn, i) in messages"
                    :key="i"
                    class="flex"
                    :class="turn.role === 'user' ? 'justify-end' : 'justify-start'"
                >
                    <div
                        class="max-w-[80%] rounded-2xl px-4 py-2 text-sm whitespace-pre-wrap"
                        :class="
                            turn.role === 'user'
                                ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                : 'bg-neutral-100 text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100'
                        "
                    >
                        {{ turn.content }}
                    </div>
                </div>

                <div v-if="sending" class="flex justify-start">
                    <div class="rounded-2xl bg-neutral-100 px-4 py-2 text-sm text-neutral-400 dark:bg-neutral-800">
                        Думаю…
                    </div>
                </div>
            </div>

            <!-- Error -->
            <div
                v-if="error"
                class="border-t border-amber-200 bg-amber-50 px-4 py-2 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300"
            >
                {{ error }}
            </div>

            <!-- Composer -->
            <form
                class="flex items-end gap-2 border-t border-neutral-200 p-3 dark:border-neutral-800"
                @submit.prevent="send"
            >
                <textarea
                    v-model="draft"
                    rows="1"
                    placeholder="Написать второму мозгу…"
                    class="max-h-32 flex-1 resize-none rounded-lg border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                    @keydown.enter.exact.prevent="send"
                />
                <button
                    type="submit"
                    :disabled="sending || !draft.trim()"
                    class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    Отправить
                </button>
            </form>
        </div>
    </AppLayout>
</template>
