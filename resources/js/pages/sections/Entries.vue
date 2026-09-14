<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, shortDate } from '@/lib/format';
import type { Entry, Section } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    section: Section;
    entries: Entry[];
    total: number | null;
}>();

const page = usePage();

// Live, entries-backed sections the owner can move an entry into.
const moveTargets = computed(() =>
    (page.props.sections as Section[]).filter(
        (s) => s.status === 'live' && s.driver === 'entries',
    ),
);

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const form = useForm<{
    body: string;
    amount: string;
    direction: 'income' | 'expense';
    occurred_at: string;
    tagsText: string;
}>({
    body: '',
    amount: '',
    direction: 'expense',
    occurred_at: today(),
    tagsText: '',
});

const storeUrl = computed(() => `/${props.section.key}/entries`);

function submit() {
    form.transform((data) => ({
        body: data.body,
        amount: props.section.money && data.amount !== '' ? data.amount : null,
        direction: props.section.money ? data.direction : null,
        occurred_at: data.occurred_at,
        tags: data.tagsText
            .split(',')
            .map((t) => t.trim())
            .filter((t) => t.length > 0),
    })).post(storeUrl.value, {
        preserveScroll: true,
        onSuccess: () => form.reset('body', 'amount', 'tagsText'),
    });
}

function remove(id: number) {
    router.delete(`/entries/${id}`, { preserveScroll: true });
}

// ---- Inline edit / move ----
const editingId = ref<number | null>(null);
const editForm = useForm<{
    section: string;
    body: string;
    amount: string;
    direction: 'income' | 'expense';
    occurred_at: string;
}>({ section: '', body: '', amount: '', direction: 'expense', occurred_at: '' });

function startEdit(entry: Entry) {
    editingId.value = entry.id;
    editForm.defaults();
    editForm.section = entry.section;
    editForm.body = entry.body;
    editForm.amount = entry.amount !== null ? String(Math.abs(entry.amount)) : '';
    editForm.direction = (entry.amount ?? 0) < 0 ? 'expense' : 'income';
    editForm.occurred_at = entry.occurred_at;
    editForm.clearErrors();
}

function cancelEdit() {
    editingId.value = null;
}

function targetIsMoney(key: string): boolean {
    return moveTargets.value.find((s) => s.key === key)?.money ?? false;
}

function saveEdit(id: number) {
    editForm.transform((d) => ({
        section: d.section,
        body: d.body,
        amount: targetIsMoney(d.section) && d.amount !== '' ? d.amount : null,
        direction: targetIsMoney(d.section) ? d.direction : null,
        occurred_at: d.occurred_at,
    })).put(`/entries/${id}`, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
        },
    });
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
                            Запись
                        </label>
                        <textarea
                            v-model="form.body"
                            rows="3"
                            required
                            placeholder="Что произошло?"
                            class="w-full resize-none rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                        <p v-if="form.errors.body" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ form.errors.body }}
                        </p>
                    </div>

                    <div v-if="section.money">
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Сумма (сум)
                        </label>
                        <div class="mb-2 inline-flex rounded-md border border-neutral-200 p-0.5 dark:border-neutral-700">
                            <button
                                type="button"
                                class="rounded px-3 py-1 text-xs font-medium transition"
                                :class="form.direction === 'expense' ? 'bg-red-500 text-white' : 'text-neutral-500'"
                                @click="form.direction = 'expense'"
                            >
                                Расход
                            </button>
                            <button
                                type="button"
                                class="rounded px-3 py-1 text-xs font-medium transition"
                                :class="form.direction === 'income' ? 'bg-emerald-500 text-white' : 'text-neutral-500'"
                                @click="form.direction = 'income'"
                            >
                                Доход
                            </button>
                        </div>
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
                            Дата
                        </label>
                        <input
                            v-model="form.occurred_at"
                            type="date"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Теги <span class="normal-case text-neutral-400">(через запятую)</span>
                        </label>
                        <input
                            v-model="form.tagsText"
                            type="text"
                            placeholder="напр. идея, срочно"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || !form.body.trim()"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                    >
                        Добавить запись
                    </button>
                </form>

                <StatCard
                    v-if="section.money && total !== null"
                    class="mt-4"
                    label="Итого"
                    :value="money(total)"
                />
            </div>

            <!-- Entry list -->
            <div class="lg:col-span-2">
                <div
                    v-if="!entries.length"
                    class="rounded-xl border border-dashed border-neutral-300 p-8 text-center text-sm text-neutral-400 dark:border-neutral-700"
                >
                    Записей пока нет. Добавь первую.
                </div>

                <ul v-else class="flex flex-col gap-3">
                    <li
                        v-for="entry in entries"
                        :key="entry.id"
                        class="group rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <!-- Display mode -->
                        <div v-if="editingId !== entry.id" class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm whitespace-pre-wrap text-neutral-900 dark:text-neutral-100">
                                    {{ entry.body }}
                                </p>
                                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                                    <span>{{ shortDate(entry.occurred_at) }}</span>
                                    <span
                                        v-if="entry.amount !== null"
                                        class="rounded px-1.5 py-0.5 font-medium tabular-nums"
                                        :class="entry.amount < 0
                                            ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-300'
                                            : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-300'"
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
                            <div class="flex shrink-0 gap-1 opacity-0 transition group-hover:opacity-100">
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-neutral-700 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                                    aria-label="Редактировать запись"
                                    @click="startEdit(entry)"
                                >
                                    <Icon name="pencil" :size="16" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md p-1.5 text-neutral-300 hover:bg-neutral-100 hover:text-red-500 dark:hover:bg-neutral-800"
                                    aria-label="Удалить запись"
                                    @click="remove(entry.id)"
                                >
                                    <Icon name="trash" :size="16" />
                                </button>
                            </div>
                        </div>

                        <!-- Edit / move mode -->
                        <form v-else class="flex flex-col gap-3" @submit.prevent="saveEdit(entry.id)">
                            <textarea
                                v-model="editForm.body"
                                rows="2"
                                required
                                class="w-full resize-none rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                            />
                            <div class="flex flex-wrap gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                                        Раздел
                                    </label>
                                    <select
                                        v-model="editForm.section"
                                        class="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                                    >
                                        <option v-for="t in moveTargets" :key="t.key" :value="t.key">
                                            {{ t.label }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                                        Дата
                                    </label>
                                    <input
                                        v-model="editForm.occurred_at"
                                        type="date"
                                        class="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                                    />
                                </div>
                                <div v-if="targetIsMoney(editForm.section)">
                                    <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                                        Сумма (сум)
                                    </label>
                                    <div class="flex items-center gap-2">
                                        <select
                                            v-model="editForm.direction"
                                            class="rounded-md border border-neutral-300 bg-white px-2 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-950"
                                        >
                                            <option value="expense">Расход</option>
                                            <option value="income">Доход</option>
                                        </select>
                                        <input
                                            v-model="editForm.amount"
                                            type="number"
                                            min="0"
                                            step="1"
                                            class="w-32 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                                        />
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button
                                    type="submit"
                                    :disabled="editForm.processing"
                                    class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900"
                                >
                                    Сохранить
                                </button>
                                <button
                                    type="button"
                                    class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                                    @click="cancelEdit"
                                >
                                    Отмена
                                </button>
                            </div>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </AppLayout>
</template>
