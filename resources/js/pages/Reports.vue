<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { money } from '@/lib/format';
import type { MoneyReport, PeriodMeta, PeriodType } from '@/types';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    period: PeriodMeta;
    types: PeriodType[];
    report: MoneyReport;
    error: string | null;
}>();

const typeLabels: Record<PeriodType, string> = {
    day: 'День',
    week: 'Неделя',
    month: 'Месяц',
    quarter: 'Квартал',
    year: 'Год',
};

function go(type: PeriodType, date?: string) {
    router.get(
        '/reports',
        { period: type, ...(date ? { date } : {}) },
        { preserveScroll: true, preserveState: true },
    );
}

function selectType(type: PeriodType) {
    // Switching the window resets the anchor to the latest (today).
    go(type);
}

function step(date: string) {
    go(props.period.type, date);
}
</script>

<template>
    <Head title="Отчёты" />

    <AppLayout title="Отчёты">
        <div class="mb-6">
            <h2 class="text-xl font-semibold">Отчёты</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Все деньги — Atheer, бюджет и домашний бизнес — за период.
            </p>
        </div>

        <!-- Period type tabs -->
        <div class="mb-4 inline-flex rounded-lg border border-neutral-200 bg-white p-1 dark:border-neutral-800 dark:bg-neutral-900">
            <button
                v-for="t in types"
                :key="t"
                type="button"
                class="rounded-md px-3 py-1.5 text-sm font-medium transition"
                :class="
                    t === period.type
                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                        : 'text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100'
                "
                @click="selectType(t)"
            >
                {{ typeLabels[t] }}
            </button>
        </div>

        <!-- Period navigator -->
        <div class="mb-6 flex items-center gap-3">
            <button
                type="button"
                class="rounded-md border border-neutral-300 p-1.5 text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                aria-label="Предыдущий период"
                @click="step(period.previous)"
            >
                <Icon name="left" :size="18" />
            </button>
            <span class="min-w-48 text-center text-sm font-medium">{{ period.label }}</span>
            <button
                type="button"
                :disabled="!period.canGoNext"
                class="rounded-md border border-neutral-300 p-1.5 text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                aria-label="Следующий период"
                @click="period.canGoNext && step(period.next)"
            >
                <Icon name="right" :size="18" />
            </button>
        </div>

        <!-- Unavailable banner -->
        <div
            v-if="error"
            class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300"
        >
            {{ error }}
        </div>

        <!-- Totals -->
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard label="Доходы" :value="money(report.income)" tone="positive" />
            <StatCard label="Расходы" :value="money(report.expense)" tone="negative" />
            <StatCard label="Чистая прибыль" :value="money(report.net)" :tone="report.net >= 0 ? 'positive' : 'negative'" />
            <StatCard label="Отдано на садака" :value="money(report.charity_given)" sub="не входит в чистую прибыль" />
        </div>

        <!-- Per-source breakdown -->
        <h3 class="mt-8 mb-3 text-sm font-semibold tracking-wide text-neutral-500 uppercase">
            По источникам
        </h3>
        <div class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800">
            <table class="w-full text-sm">
                <thead class="bg-neutral-50 text-left text-xs tracking-wide text-neutral-500 uppercase dark:bg-neutral-900">
                    <tr>
                        <th class="px-4 py-2 font-medium">Источник</th>
                        <th class="px-4 py-2 text-right font-medium">Доходы</th>
                        <th class="px-4 py-2 text-right font-medium">Расходы</th>
                        <th class="px-4 py-2 text-right font-medium">Итого</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="s in report.sources"
                        :key="s.key"
                        class="border-t border-neutral-100 dark:border-neutral-800/70"
                    >
                        <td class="px-4 py-2.5">
                            {{ s.label }}
                            <span
                                v-if="!s.available"
                                class="ml-1 text-xs text-amber-600 dark:text-amber-400"
                                >(нет данных)</span
                            >
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ money(s.income) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums">{{ money(s.expense) }}</td>
                        <td
                            class="px-4 py-2.5 text-right font-medium tabular-nums"
                            :class="s.net >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
                        >
                            {{ money(s.net) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
