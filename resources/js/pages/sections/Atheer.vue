<script setup lang="ts">
import StatCard from '@/components/StatCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { count, money, shortDate } from '@/lib/format';
import type { AtheerSummary } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    report: AtheerSummary | null;
    error: string | null;
}>();

const finance = computed(() => props.report?.finance_month ?? null);
const deliveries = computed(() => props.report?.deliveries ?? null);
const reconciliation = computed(() => props.report?.reconciliation ?? null);

// Funnel bars scale against the busiest stage.
const funnelMax = computed(() =>
    Math.max(1, ...(props.report?.funnel ?? []).map((s) => s.count)),
);

const generatedAt = computed(() => {
    if (!props.report) return null;
    return new Date(props.report.generated_at).toLocaleString('ru-RU');
});

function refresh() {
    router.reload({ only: ['report', 'error'] });
}
</script>

<template>
    <Head title="Atheer" />

    <AppLayout title="Atheer">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold">Atheer</h2>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    <span v-if="generatedAt">На {{ generatedAt }}</span>
                    <span v-else>Актуальные показатели бизнеса.</span>
                </p>
            </div>
            <button
                type="button"
                class="rounded-md border border-neutral-300 px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                @click="refresh"
            >
                Обновить
            </button>
        </div>

        <!-- Unavailable banner -->
        <div
            v-if="error"
            class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-700 dark:border-amber-900/50 dark:bg-amber-900/20 dark:text-amber-300"
        >
            {{ error }} Данные ниже не загрузились — нажми «Обновить».
        </div>

        <template v-if="report">
            <!-- Finance (this month) -->
            <h3 class="mb-3 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
                Этот месяц
                <span v-if="finance" class="font-normal text-neutral-400">
                    ({{ shortDate(finance.from) }} – {{ shortDate(finance.to) }})
                </span>
            </h3>
            <div v-if="finance" class="mb-8 grid grid-cols-2 gap-3 lg:grid-cols-5">
                <StatCard label="Выручка" :value="money(finance.revenue)" />
                <StatCard label="Прибыль" :value="money(finance.profit)" tone="positive" />
                <StatCard label="Возвраты" :value="money(finance.refunds)" tone="negative" />
                <StatCard label="Итого" :value="money(finance.net)" />
                <StatCard label="Заказы" :value="count(finance.orders_count)" />
            </div>

            <!-- Deliveries right now -->
            <h3 class="mb-3 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
                Доставки сейчас
            </h3>
            <div v-if="deliveries" class="mb-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
                <StatCard label="В пути" :value="count(deliveries.in_transit)" />
                <StatCard label="Доставлено сегодня" :value="count(deliveries.delivered_today)" tone="positive" />
                <StatCard label="Проблемные" :value="count(deliveries.flagged)" :tone="deliveries.flagged ? 'warning' : 'default'" />
                <StatCard label="Сумма проблемных" :value="money(deliveries.flagged_amount)" :tone="deliveries.flagged_amount ? 'warning' : 'default'" />
            </div>

            <!-- In-transit table -->
            <div
                v-if="deliveries && deliveries.in_transit_list.length"
                class="mb-8 overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800"
            >
                <table class="min-w-full text-sm">
                    <thead class="bg-neutral-50 text-left text-xs text-neutral-500 uppercase dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Получатель</th>
                            <th class="px-4 py-2 font-medium">Телефон</th>
                            <th class="px-4 py-2 text-right font-medium">Наложенный платёж</th>
                            <th class="px-4 py-2 font-medium">Отправлено</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <tr v-for="d in deliveries.in_transit_list" :key="d.id">
                            <td class="px-4 py-2">{{ d.recipient_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ d.recipient_phone ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money(d.cod_amount) }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ shortDate(d.dispatched_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Reconciliation flagged -->
            <h3 class="mb-3 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
                К сверке
                <span v-if="reconciliation" class="font-normal text-neutral-400">
                    ({{ count(reconciliation.flagged_count) }} проблемных · {{ money(reconciliation.flagged_amount) }})
                </span>
            </h3>
            <div
                v-if="reconciliation && reconciliation.items.length"
                class="overflow-x-auto rounded-xl border border-neutral-200 dark:border-neutral-800"
            >
                <table class="min-w-full text-sm">
                    <thead class="bg-neutral-50 text-left text-xs text-neutral-500 uppercase dark:bg-neutral-900 dark:text-neutral-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Получатель</th>
                            <th class="px-4 py-2 font-medium">Телефон</th>
                            <th class="px-4 py-2 text-right font-medium">Наложенный платёж</th>
                            <th class="px-4 py-2 font-medium">Отправлено</th>
                            <th class="px-4 py-2 font-medium">Причина</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 dark:divide-neutral-800">
                        <tr v-for="d in reconciliation.items" :key="d.id">
                            <td class="px-4 py-2">{{ d.recipient_name ?? '—' }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ d.recipient_phone ?? '—' }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ money(d.cod_amount) }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ shortDate(d.dispatched_at) }}</td>
                            <td class="px-4 py-2 text-neutral-500">{{ d.flag_reason ?? '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-else-if="reconciliation"
                class="rounded-xl border border-neutral-200 bg-white p-4 text-sm text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900"
            >
                Нечего сверять — все доставки закрыты.
            </p>

            <!-- Lead funnel -->
            <h3 class="mt-8 mb-3 text-sm font-semibold text-neutral-500 dark:text-neutral-400">
                Воронка лидов
            </h3>
            <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <div
                    v-for="stage in report.funnel"
                    :key="stage.stage"
                    class="flex items-center gap-3 py-1.5"
                >
                    <span class="w-40 shrink-0 truncate text-sm text-neutral-600 dark:text-neutral-300">
                        {{ stage.label }}
                    </span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        <div
                            class="h-full rounded-full bg-neutral-800 dark:bg-neutral-200"
                            :style="{ width: `${(stage.count / funnelMax) * 100}%` }"
                        />
                    </div>
                    <span class="w-10 shrink-0 text-right text-sm tabular-nums text-neutral-500">
                        {{ count(stage.count) }}
                    </span>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
