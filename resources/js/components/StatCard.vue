<script setup lang="ts">
withDefaults(
    defineProps<{
        label: string;
        value: string;
        sub?: string;
        tone?: 'default' | 'positive' | 'warning' | 'negative';
    }>(),
    { tone: 'default' },
);

const toneClass: Record<string, string> = {
    default: 'text-neutral-900 dark:text-neutral-100',
    positive: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    negative: 'text-red-600 dark:text-red-400',
};
</script>

<template>
    <div class="rounded-xl border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
        <p class="text-xs font-medium tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
            {{ label }}
        </p>
        <p class="mt-1 text-2xl font-semibold tabular-nums" :class="toneClass[tone]">
            {{ value }}
        </p>
        <p v-if="sub" class="mt-0.5 text-xs text-neutral-400">{{ sub }}</p>
    </div>
</template>
