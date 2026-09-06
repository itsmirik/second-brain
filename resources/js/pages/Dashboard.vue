<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Section } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const sections = computed<Section[]>(() => page.props.sections);
const ownerName = computed(() => page.props.auth.user.name);
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout title="Dashboard">
        <div class="mb-8">
            <h2 class="text-xl font-semibold">Welcome back, {{ ownerName }}</h2>
            <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                Your life, one section at a time.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="section.status === 'live' ? Link : 'div'"
                v-for="section in sections"
                :key="section.key"
                :href="section.status === 'live' ? `/${section.key}` : undefined"
                class="flex flex-col rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900"
                :class="
                    section.status === 'live'
                        ? 'transition hover:border-neutral-300 hover:shadow-sm dark:hover:border-neutral-700'
                        : ''
                "
            >
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-neutral-100 text-neutral-700 dark:bg-neutral-800 dark:text-neutral-200">
                        <Icon :name="section.icon" />
                    </div>
                    <span
                        :class="[
                            'rounded-full px-2 py-0.5 text-[11px] font-medium tracking-wide uppercase',
                            section.status === 'live'
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300'
                                : 'bg-neutral-100 text-neutral-400 dark:bg-neutral-800',
                        ]"
                    >
                        {{ section.status === 'live' ? 'Live' : 'Soon' }}
                    </span>
                </div>
                <h3 class="font-semibold">{{ section.label }}</h3>
                <p class="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                    {{ section.description }}
                </p>
            </component>
        </div>
    </AppLayout>
</template>
