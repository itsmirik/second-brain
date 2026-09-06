<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { count, money } from '@/lib/format';
import type { Finance, PeriodMeta, PeriodType } from '@/types';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps<{
    period: PeriodMeta;
    types: PeriodType[];
    finance: Finance | null;
    error: string | null;
}>();

const typeLabels: Record<PeriodType, string> = {
    day: 'Day',
    week: 'Week',
    month: 'Month',
    quarter: 'Quarter',
    year: 'Year',
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
    <Head title="Reports" />

    <AppLayout title="Reports">
        <div class="mb-6">
            <h2 class="text-xl font-semibold">Reports</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Business finance over a period.
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
                aria-label="Previous period"
                @click="step(period.previous)"
            >
                <Icon name="left" :size="18" />
            </button>
            <span class="min-w-48 text-center text-sm font-medium">{{ period.label }}</span>
            <button
                type="button"
                :disabled="!period.canGoNext"
                class="rounded-md border border-neutral-300 p-1.5 text-neutral-600 hover:bg-neutral-100 disabled:opacity-40 disabled:hover:bg-transparent dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                aria-label="Next period"
                @click="period.canGoNext && step(period.next)"
            >
                <Icon name="right" :size="18" />
            </button>
        </div>

        <!-- Unavailable banner -->
        <div
            v-if="error"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300"
        >
            {{ error }} Figures could not be loaded for this period.
        </div>

        <!-- Finance figures -->
        <div v-else-if="finance" class="grid grid-cols-2 gap-3 lg:grid-cols-5">
            <StatCard label="Revenue" :value="money(finance.revenue)" />
            <StatCard label="Profit" :value="money(finance.profit)" tone="positive" />
            <StatCard label="Refunds" :value="money(finance.refunds)" tone="negative" />
            <StatCard label="Net" :value="money(finance.net)" />
            <StatCard label="Orders" :value="count(finance.orders_count)" />
        </div>
    </AppLayout>
</template>
