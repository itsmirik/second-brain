<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, shortDate } from '@/lib/format';
import type { Entry, Section } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    section: Section;
    entries: Entry[];
    total: number | null;
}>();

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const form = useForm<{
    body: string;
    amount: string;
    occurred_at: string;
    tagsText: string;
}>({
    body: '',
    amount: '',
    occurred_at: today(),
    tagsText: '',
});

const storeUrl = computed(() => `/${props.section.key}/entries`);

function submit() {
    form
        .transform((data) => ({
            body: data.body,
            amount: props.section.money && data.amount !== '' ? data.amount : null,
            occurred_at: data.occurred_at,
            tags: data.tagsText
                .split(',')
                .map((t) => t.trim())
                .filter((t) => t.length > 0),
        }))
        .post(storeUrl.value, {
            preserveScroll: true,
            onSuccess: () => form.reset('body', 'amount', 'tagsText'),
        });
}

function remove(id: number) {
    router.delete(`/entries/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="section.label" />

    <AppLayout :title="section.label">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h2 class="text-xl font-semibold">{{ section.label }}</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ section.description }}
                </p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Capture form -->
            <div class="lg:col-span-1">
                <form
                    class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    @submit.prevent="submit"
                >
                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Entry
                        </label>
                        <textarea
                            v-model="form.body"
                            rows="3"
                            required
                            placeholder="What happened?"
                            class="w-full resize-none rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ form.errors.body }}
                        </p>
                    </div>

                    <div v-if="section.money">
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Amount (so'm)
                        </label>
                        <input
                            v-model="form.amount"
                            type="number"
                            min="0"
                            step="1"
                            inputmode="numeric"
                            placeholder="0"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Date
                        </label>
                        <input
                            v-model="form.occurred_at"
                            type="date"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Tags <span class="normal-case text-neutral-400">(comma-separated)</span>
                        </label>
                        <input
                            v-model="form.tagsText"
                            type="text"
                            placeholder="e.g. idea, urgent"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.body.trim()"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        Add entry
                    </button>
                </form>

                <StatCard
                    v-if="section.money && total !== null"
                    class="mt-4"
                    label="Total logged"
                    :value="money(total)"
                />
            </div>

            <!-- Entry list -->
            <div class="lg:col-span-2">
                <div
                    v-if="!entries.length"
                    class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-sm text-neutral-400 dark:border-neutral-700"
                >
                    No entries yet. Add the first one.
                </div>

                <ul v-else class="flex flex-col gap-3">
                    <li
                        v-for="entry in entries"
                        :key="entry.id"
                        class="group rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm whitespace-pre-wrap text-neutral-900 dark:text-neutral-100">
                                    {{ entry.body }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                                    <span>{{ shortDate(entry.occurred_at) }}</span>
                                    <span
                                        v-if="entry.amount !== null"
                                        class="rounded bg-neutral-100 px-1.5 py-0.5 font-medium text-neutral-600 tabular-nums dark:bg-neutral-800 dark:text-neutral-300"
                                    >
                                        {{ money(entry.amount) }}
                                    </span>
                                    <span
                                        v-for="tag in entry.tags"
                                        :key="tag"
                                        class="rounded bg-neutral-100 px-1.5 py-0.5 dark:bg-neutral-800"
                                    >
                                        #{{ tag }}
                                    </span>
                                </div>
                            </div>
                            <button
                                type="button"
                                class="shrink-0 rounded-md p-1.5 text-neutral-300 opacity-0 transition group-hover:opacity-100 hover:bg-neutral-100 hover:text-red-500 dark:hover:bg-neutral-800"
                                aria-label="Delete entry"
                                @click="remove(entry.id)"
                            >
                                <Icon name="trash" :size="16" />
                            </button>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
