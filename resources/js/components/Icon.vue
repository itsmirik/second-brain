<script setup lang="ts">
import { computed } from 'vue';

// Minimal inline icon set (stroke-based, currentColor). Avoids pulling in a
// full icon dependency for the handful of glyphs the dashboard needs.
const paths: Record<string, string[]> = {
    store: [
        'M3 9l1.5-5h15L21 9',
        'M4 9v9a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1V9',
        'M3 9h18',
    ],
    user: ['M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z', 'M4 20a8 8 0 0 1 16 0'],
    home: ['M3 11l9-8 9 8', 'M5 10v10h14V10'],
    heart: [
        'M12 20s-7-4.6-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.4-7 10-7 10Z',
    ],
    wallet: [
        'M3 7a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2Z',
        'M16 12h4',
    ],
    users: [
        'M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z',
        'M3 20a6 6 0 0 1 12 0',
        'M16 5.5a3 3 0 0 1 0 5.9',
        'M18 20a6 6 0 0 0-3-5.2',
    ],
    grid: ['M4 4h7v7H4z', 'M13 4h7v7h-7z', 'M4 13h7v7H4z', 'M13 13h7v7h-7z'],
    logout: ['M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4', 'M10 17l5-5-5-5', 'M15 12H3'],
    menu: ['M4 6h16', 'M4 12h16', 'M4 18h16'],
    chat: ['M21 15a2 2 0 0 1-2 2H8l-4 4V6a2 2 0 0 1 2-2h13a2 2 0 0 1 2 2Z'],
    chart: ['M4 20V4', 'M4 20h16', 'M8 16v-4', 'M13 16V8', 'M18 16v-6'],
    left: ['M15 6l-6 6 6 6'],
    right: ['M9 6l6 6-6 6'],
    trash: ['M4 7h16', 'M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2', 'M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13'],
};

const props = withDefaults(
    defineProps<{ name: string; size?: number }>(),
    { size: 20 },
);

const d = computed(() => paths[props.name] ?? []);
</script>

<template>
    <svg
        :width="size"
        :height="size"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.75"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path v-for="(p, i) in d" :key="i" :d="p" />
    </svg>
</template>
