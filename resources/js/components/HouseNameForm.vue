<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

// Matches House::NAME_MAX on the server.
const NAME_MAX = 100;

const props = withDefaults(
    defineProps<{
        action: string;
        method: 'post' | 'put';
        submitLabel: string;
        initial?: string;
    }>(),
    { initial: '' },
);

const emit = defineEmits<{ done: []; cancel: [] }>();

const form = useForm({ name: props.initial });
const input = ref<HTMLInputElement | null>(null);

// Ready to type: focused, and a name being renamed is selected.
onMounted(() => input.value?.select());

function submit() {
    form.submit(props.method, props.action, {
        preserveScroll: true,
        onSuccess: () => emit('done'),
    });
}
</script>

<template>
    <form @submit.prevent="submit">
        <div class="flex flex-wrap gap-2">
            <input
                ref="input"
                v-model="form.name"
                type="text"
                :maxlength="NAME_MAX"
                required
                aria-label="Название дома"
                placeholder="Название дома, напр. Чиланзар"
                class="min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none focus:border-neutral-900 dark:border-neutral-700 dark:bg-neutral-950 dark:focus:border-neutral-100"
                @keydown.esc="emit('cancel')"
            />
            <button
                type="submit"
                :disabled="form.processing || !form.name.trim()"
                class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white transition hover:bg-neutral-800 disabled:opacity-40 dark:bg-white dark:text-neutral-900 dark:hover:bg-neutral-200"
            >
                {{ submitLabel }}
            </button>
            <button
                type="button"
                class="rounded-md border border-neutral-300 px-3 py-2 text-sm text-neutral-600 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                @click="emit('cancel')"
            >
                Отмена
            </button>
        </div>
        <p v-if="form.errors.name" class="mt-1 text-xs text-red-600 dark:text-red-400">
            {{ form.errors.name }}
        </p>
    </form>
</template>
