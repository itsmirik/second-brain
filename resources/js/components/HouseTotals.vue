<script setup lang="ts">
import { money } from '@/lib/format';
import { showHouse } from '@/lib/houses';
import type { HouseFilter, HouseSummary, MoneyTotals } from '@/types';
import { computed } from 'vue';

const props = defineProps<{
    sectionKey: string;
    houses: HouseSummary[];
    // Entries filed under no house; null when there are none.
    unassigned: MoneyTotals | null;
}>();

// One row per house, plus the entries under no house, so the rows add up to
// the section total. Each row opens that view.
const rows = computed(() => [
    ...props.houses.map((h) => ({
        key: `house-${h.id}`,
        label: h.name,
        totals: h.totals,
        filter: h.id as HouseFilter,
    })),
    ...(props.unassigned
        ? [{ key: 'none', label: 'Без дома', totals: props.unassigned, filter: 'none' as HouseFilter }]
        : []),
]);

function netTone(net: number): string {
    if (net > 0) return 'text-emerald-600 dark:text-emerald-400';
    if (net < 0) return 'text-red-600 dark:text-red-400';
    return 'text-neutral-500';
}
</script>

<template>
    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
        <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
            По домам
        </p>
        <ul class="mt-1 divide-y divide-neutral-100 dark:divide-neutral-800">
            <li v-for="row in rows" :key="row.key">
                <button
                    type="button"
                    class="flex w-full items-start justify-between gap-3 py-2.5 text-left"
                    @click="showHouse(sectionKey, row.filter)"
                >
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium">{{ row.label }}</span>
                        <!-- Wraps between the two figures, never inside one. -->
                        <span class="block text-xs text-neutral-400">
                            <span class="whitespace-nowrap">доходы {{ money(row.totals.income) }}</span>
                            ·
                            <span class="whitespace-nowrap">расходы {{ money(row.totals.expense) }}</span>
                        </span>
                    </span>
                    <span class="shrink-0 text-sm font-medium tabular-nums" :class="netTone(row.totals.net)">
                        {{ money(row.totals.net) }}
                    </span>
                </button>
            </li>
        </ul>
    </div>
</template>
