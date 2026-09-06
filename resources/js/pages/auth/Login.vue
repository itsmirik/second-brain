<script setup lang="ts">
import Icon from '@/components/Icon.vue';
import { Head, useForm } from '@inertiajs/vue3';

defineProps<{
    canResetPassword?: boolean;
    status?: string;
}>();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <Head title="Log in" />

    <div class="flex min-h-screen items-center justify-center bg-neutral-50 p-6 dark:bg-neutral-950">
        <div class="w-full max-w-sm">
            <div class="mb-8 flex flex-col items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-neutral-900 text-white dark:bg-white dark:text-neutral-900">
                    <Icon name="grid" :size="24" />
                </div>
                <h1 class="text-lg font-semibold text-neutral-900 dark:text-neutral-100">
                    Sign in
                </h1>
            </div>

            <div
                v-if="status"
                class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300"
            >
                {{ status }}
            </div>

            <form
                class="flex flex-col gap-4 rounded-xl border border-neutral-200 bg-white p-6 dark:border-neutral-800 dark:bg-neutral-900"
                @submit.prevent="submit"
            >
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300" for="email">
                        Email
                    </label>
                    <input
                        id="email"
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        autofocus
                        required
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                    />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700 dark:text-neutral-300" for="password">
                        Password
                    </label>
                    <input
                        id="password"
                        v-model="form.password"
                        type="password"
                        autocomplete="current-password"
                        required
                        class="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                    />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">
                        {{ form.errors.password }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                    <input v-model="form.remember" type="checkbox" class="rounded border-neutral-300" />
                    Remember me
                </label>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="mt-2 rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-50 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
                >
                    {{ form.processing ? 'Signing in…' : 'Sign in' }}
                </button>
            </form>
        </div>
    </div>
</template>
