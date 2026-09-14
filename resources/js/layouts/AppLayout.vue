<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import type { Section } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{ title?: string }>();

const page = usePage();
const appName = computed(() => page.props.name);
const user = computed(() => page.props.auth.user);
const sections = computed<Section[]>(
    () => (page.props.sections as Section[] | undefined) ?? [],
);

const currentPath = computed(() => new URL(page.url, 'http://x').pathname);
const isActive = (path: string) => currentPath.value === path;

const mobileOpen = ref(false);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-neutral-50 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
        <!-- Sidebar -->
        <aside
            :class="[
                'fixed inset-y-0 left-0 z-40 w-64 transform border-r border-neutral-200 bg-white transition-transform lg:translate-x-0 dark:border-neutral-800 dark:bg-neutral-900',
                mobileOpen ? 'translate-x-0' : '-translate-x-full',
            ]"
        >
            <div class="flex h-16 items-center gap-2 border-b border-neutral-200 px-5 dark:border-neutral-800">
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                    <Icon name="grid" :size="18" />
                </div>
                <span class="font-semibold">{{ appName }}</span>
            </div>

            <nav class="flex flex-col gap-1 p-3">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        isActive('/dashboard')
                            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                            : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                    "
                >
                    <Icon name="grid" :size="18" />
                    Главная
                </Link>

                <Link
                    href="/reports"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        isActive('/reports')
                            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                            : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                    "
                >
                    <Icon name="chart" :size="18" />
                    Отчёты
                </Link>

                <Link
                    href="/chat"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                    :class="
                        isActive('/chat')
                            ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                            : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                    "
                >
                    <Icon name="chat" :size="18" />
                    Чат
                </Link>

                <p class="px-3 pt-4 pb-1 text-xs font-semibold tracking-wide text-neutral-400 uppercase">
                    Разделы
                </p>

                <template v-for="section in sections" :key="section.key">
                    <!-- Live sections navigate; planned ones are muted labels. -->
                    <Link
                        v-if="section.status === 'live'"
                        :href="`/${section.key}`"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium"
                        :class="
                            isActive(`/${section.key}`)
                                ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                : 'text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800'
                        "
                    >
                        <Icon :name="section.icon" :size="18" />
                        {{ section.label }}
                    </Link>
                    <div
                        v-else
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm text-neutral-500 dark:text-neutral-400"
                    >
                        <Icon :name="section.icon" :size="18" />
                        <span class="flex-1">{{ section.label }}</span>
                        <span class="rounded bg-neutral-100 px-1.5 py-0.5 text-[10px] font-medium tracking-wide text-neutral-400 uppercase dark:bg-neutral-800">
                            скоро
                        </span>
                    </div>
                </template>
            </nav>
        </aside>

        <!-- Backdrop for mobile -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-30 bg-black/40 lg:hidden"
            @click="mobileOpen = false"
        />

        <!-- Main -->
        <div class="lg:pl-64">
            <header
                class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-neutral-200 bg-white/80 px-4 backdrop-blur dark:border-neutral-800 dark:bg-neutral-900/80 sm:px-6"
            >
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-md p-2 text-neutral-500 hover:bg-neutral-100 lg:hidden dark:hover:bg-neutral-800"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Icon name="menu" />
                    </button>
                    <h1 class="text-base font-semibold">{{ props.title ?? 'Главная' }}</h1>
                </div>

                <div class="flex items-center gap-3">
                    <span class="hidden text-sm text-neutral-500 sm:block dark:text-neutral-400">
                        {{ user.name }}
                    </span>
                    <button
                        type="button"
                        class="flex items-center gap-2 rounded-md px-3 py-1.5 text-sm text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800"
                        @click="logout"
                    >
                        <Icon name="logout" :size="18" />
                        <span class="hidden sm:inline">Выйти</span>
                    </button>
                </div>
            </header>

            <main class="mx-auto max-w-6xl p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
