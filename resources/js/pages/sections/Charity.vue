<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { money, shortDate } from '@/lib/format';
import type { CharityGiving, CharityMonth } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    percentage: number;
    months: CharityMonth[];
    selected: CharityMonth;
    giving: CharityGiving[];
}>();

function today(): string {
    return new Date().toISOString().slice(0, 10);
}

const pctForm = useForm({ percentage: props.percentage });
const profitForm = useForm({
    month: props.selected.month,
    profit: props.selected.is_override ? String(props.selected.profit) : '',
});
const giveForm = useForm({ body: '', amount: '', occurred_at: today() });

const remainingTone = computed(() =>
    props.selected.remaining > 0 ? 'warning' : 'positive',
);

function savePct() {
    pctForm.put('/charity/settings', { preserveScroll: true });
}

function selectMonth(month: string) {
    router.get('/charity', { month }, { preserveScroll: true, preserveState: false });
}

function saveProfit() {
    profitForm
        .transform((d) => ({ month: d.month, profit: d.profit === '' ? null : d.profit }))
        .put('/charity/profit', { preserveScroll: true });
}

function clearProfit() {
    profitForm.profit = '';
    saveProfit();
}

function addGiving() {
    giveForm.post('/charity/giving', {
        preserveScroll: true,
        onSuccess: () => giveForm.reset('body', 'amount'),
    });
}

function removeGiving(id: number) {
    router.delete(`/entries/${id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Садака" />

    <AppLayout title="Садака">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Благотворительность · Садака</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    Каждый месяц к выплате {{ selected.percentage }}% от прибыли. Отмечай, что отдал.
                </p>
            </div>

            <!-- Percentage setting -->
            <form
                class="flex items-end gap-2 rounded-xl border border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900"
                @submit.prevent="savePct"
            >
                <div>
                    <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                        Процент садака
                    </label>
                    <input
                        v-model="pctForm.percentage"
                        type="number"
                        min="0"
                        max="100"
                        step="0.1"
                        class="w-24 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                    />
                </div>
                <button
                    type="submit"
                    :disabled="pctForm.processing"
                    class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900"
                >
                    Сохранить
                </button>
            </form>
        </div>

        <!-- Month picker -->
        <div class="mb-5 flex items-center gap-2">
            <label class="text-sm text-neutral-500 dark:text-neutral-400">Месяц</label>
            <select
                :value="selected.month"
                class="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                @change="selectMonth(($event.target as HTMLSelectElement).value)"
            >
                <option v-for="m in months" :key="m.month" :value="m.month">
                    {{ m.label }}
                </option>
            </select>
        </div>

        <!-- Selected month summary -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Прибыль"
                :value="money(selected.profit)"
                :sub="selected.is_override ? 'указана вручную' : 'рассчитано автоматически'"
            />
            <StatCard label="К выплате" :value="money(selected.obligation)" tone="warning" />
            <StatCard label="Отдано" :value="money(selected.given)" tone="positive" />
            <StatCard
                :label="selected.remaining > 0 ? 'Осталось отдать' : 'Выполнено'"
                :value="money(Math.max(0, selected.remaining))"
                :tone="remainingTone"
            />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <!-- Left column: giving form + profit override -->
            <div class="flex flex-col gap-4 lg:col-span-1">
                <form
                    class="flex flex-col gap-3 rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    @submit.prevent="addGiving"
                >
                    <h3 class="text-sm font-semibold">Записать пожертвование</h3>
                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Куда / кому
                        </label>
                        <textarea
                            v-model="giveForm.body"
                            rows="2"
                            required
                            placeholder="напр. строительство мечети"
                            class="w-full resize-none rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                        <p v-if="giveForm.errors.body" class="mt-1 text-xs text-red-600 dark:text-red-400">
                            {{ giveForm.errors.body }}
                        </p>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Сумма (сум)
                        </label>
                        <input
                            v-model="giveForm.amount"
                            type="number"
                            min="0"
                            step="1"
                            required
                            placeholder="0"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium tracking-wide text-neutral-500 uppercase">
                            Дата
                        </label>
                        <input
                            v-model="giveForm.occurred_at"
                            type="date"
                            class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                        />
                    </div>
                    <button
                        type="submit"
                        :disabled="giveForm.processing || !giveForm.body.trim() || giveForm.amount === ''"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900"
                    >
                        Добавить пожертвование
                    </button>
                </form>

                <!-- Manual profit override -->
                <form
                    class="flex flex-col gap-3 rounded-xl border border-dashed border-neutral-300 p-4 dark:border-neutral-700"
                    @submit.prevent="saveProfit"
                >
                    <h3 class="text-sm font-semibold">Задать прибыль месяца вручную</h3>
                    <p class="text-xs text-neutral-500 dark:text-neutral-400">
                        Оставь пустым, чтобы использовать автоматический итог
                        ({{ money(selected.auto_profit) }}).
                    </p>
                    <input
                        v-model="profitForm.profit"
                        type="number"
                        step="1"
                        :placeholder="String(selected.auto_profit)"
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                    />
                    <div class="flex gap-2">
                        <button
                            type="submit"
                            :disabled="profitForm.processing"
                            class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900"
                        >
                            Сохранить
                        </button>
                        <button
                            v-if="selected.is_override"
                            type="button"
                            class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                            @click="clearProfit"
                        >
                            Сбросить на авто
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right column: donations this month + all months -->
            <div class="lg:col-span-2">
                <h3 class="mb-3 text-sm font-semibold">Пожертвования за {{ selected.label }}</h3>
                <div
                    v-if="!giving.length"
                    class="rounded-xl border border-dashed border-neutral-300 p-6 text-center text-sm text-neutral-400 dark:border-neutral-700"
                >
                    В этом месяце пока ничего не отдано.
                </div>
                <ul v-else class="mb-8 flex flex-col gap-2">
                    <li
                        v-for="g in giving"
                        :key="g.id"
                        class="group flex items-center justify-between gap-3 rounded-xl border border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm">{{ g.body }}</p>
                            <p class="text-xs text-neutral-400">{{ shortDate(g.occurred_at) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-medium tabular-nums">{{ money(g.amount) }}</span>
                            <button
                                type="button"
                                class="rounded-md p-1.5 text-neutral-300 opacity-0 transition group-hover:opacity-100 hover:bg-neutral-100 hover:text-red-500 dark:hover:bg-neutral-800"
                                aria-label="Удалить пожертвование"
                                @click="removeGiving(g.id)"
                            >
                                <Icon name="trash" :size="16" />
                            </button>
                        </div>
                    </li>
                </ul>

                <h3 class="mb-3 text-sm font-semibold">Все месяцы</h3>
                <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
                    <table class="w-full text-sm">
                        <thead class="bg-neutral-50 text-left text-xs tracking-wide text-neutral-500 uppercase dark:bg-neutral-900">
                            <tr>
                                <th class="px-3 py-2 font-medium">Месяц</th>
                                <th class="px-3 py-2 text-right font-medium">Прибыль</th>
                                <th class="px-3 py-2 text-right font-medium">К выплате</th>
                                <th class="px-3 py-2 text-right font-medium">Отдано</th>
                                <th class="px-3 py-2 text-right font-medium">Осталось</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="m in months"
                                :key="m.month"
                                class="cursor-pointer border-t border-neutral-100 hover:bg-neutral-50 dark:border-neutral-800/70 dark:hover:bg-neutral-900/60"
                                :class="m.month === selected.month ? 'bg-neutral-50 dark:bg-neutral-900/60' : ''"
                                @click="selectMonth(m.month)"
                            >
                                <td class="px-3 py-2.5">
                                    {{ m.label }}
                                    <span v-if="m.is_override" class="ml-1 text-xs text-neutral-400">✎</span>
                                </td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ money(m.profit) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ money(m.obligation) }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ money(m.given) }}</td>
                                <td
                                    class="px-3 py-2.5 text-right font-medium tabular-nums"
                                    :class="m.remaining > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'"
                                >
                                    {{ money(Math.max(0, m.remaining)) }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
