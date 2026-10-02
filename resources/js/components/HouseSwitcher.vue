<script setup lang="ts">
import HouseNameForm from '@/components/HouseNameForm.vue';
import Icon from '@/components/Icon.vue';
import { showHouse } from '@/lib/houses';
import type { HouseFilter, HouseSummary } from '@/types';
import { router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps<{
    sectionKey: string;
    houses: HouseSummary[];
    selected: HouseFilter;
    // Some entries are filed under no house, so that view is worth offering.
    hasUnassigned: boolean;
}>();

const current = computed(() =>
    typeof props.selected === 'number'
        ? (props.houses.find((h) => h.id === props.selected) ?? null)
        : null,
);

const adding = ref(false);
const renaming = ref(false);
const confirmingDelete = ref(false);

function startAdding() {
    renaming.value = false;
    confirmingDelete.value = false;
    adding.value = true;
}

function remove(id: number) {
    // The server keeps the house's entries (now under no house) and opens all houses.
    router.delete(`/${props.sectionKey}/houses/${id}`, { preserveScroll: true });
}

// The open tab can sit past the edge of the scrolling row (a new house is
// added at the end); scroll the row — never the page — to show it.
const tabRow = ref<HTMLElement | null>(null);
const TAB_ROW_INSET = 16;

function revealSelectedTab() {
    const row = tabRow.value;
    const tab = row?.querySelector<HTMLElement>('[aria-pressed="true"]');

    if (!row || !tab) return;

    const rowBox = row.getBoundingClientRect();
    const tabBox = tab.getBoundingClientRect();

    if (tabBox.right > rowBox.right) {
        row.scrollLeft += tabBox.right - rowBox.right + TAB_ROW_INSET;
    } else if (tabBox.left < rowBox.left) {
        row.scrollLeft -= rowBox.left - tabBox.left + TAB_ROW_INSET;
    }
}

onMounted(revealSelectedTab);

// Another view is open: drop half-finished edits and the add form.
watch(
    () => props.selected,
    async () => {
        adding.value = false;
        renaming.value = false;
        confirmingDelete.value = false;
        await nextTick();
        revealSelectedTab();
    },
);

function tabTone(active: boolean): string {
    return active
        ? 'border-neutral-900 bg-neutral-900 text-white dark:border-white dark:bg-white dark:text-neutral-900'
        : 'border-neutral-200 bg-white text-neutral-600 hover:bg-neutral-100 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-300 dark:hover:bg-neutral-800';
}
</script>

<template>
    <div class="mb-6">
        <div class="flex items-center gap-2">
            <!-- On a phone the tabs scroll sideways instead of wrapping; the
                 add button stays pinned outside them, always in reach. -->
            <div
                v-if="houses.length"
                ref="tabRow"
                class="-ml-4 min-w-0 overflow-x-auto pl-4 [scrollbar-width:none] sm:ml-0 sm:pl-0 [&::-webkit-scrollbar]:hidden"
            >
                <div class="flex w-max gap-2 py-0.5">
                    <button
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium whitespace-nowrap transition"
                        :class="tabTone(selected === null)"
                        :aria-pressed="selected === null"
                        @click="showHouse(sectionKey, null)"
                    >
                        Все
                    </button>
                    <button
                        v-for="house in houses"
                        :key="house.id"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium whitespace-nowrap transition"
                        :class="tabTone(selected === house.id)"
                        :aria-pressed="selected === house.id"
                        @click="showHouse(sectionKey, house.id)"
                    >
                        <span class="block max-w-56 truncate">{{ house.name }}</span>
                    </button>
                    <button
                        v-if="hasUnassigned || selected === 'none'"
                        type="button"
                        class="rounded-full border px-3.5 py-1.5 text-sm font-medium whitespace-nowrap transition"
                        :class="tabTone(selected === 'none')"
                        :aria-pressed="selected === 'none'"
                        @click="showHouse(sectionKey, 'none')"
                    >
                        Без дома
                    </button>
                </div>
            </div>

            <button
                v-if="!adding"
                type="button"
                class="flex shrink-0 items-center gap-1 rounded-full border border-dashed border-neutral-300 px-3.5 py-1.5 text-sm font-medium whitespace-nowrap text-neutral-600 transition hover:border-neutral-400 hover:bg-neutral-100 dark:border-neutral-700 dark:text-neutral-300 dark:hover:bg-neutral-800"
                @click="startAdding"
            >
                <Icon name="plus" :size="16" />
                {{ houses.length ? 'Дом' : 'Добавить дом' }}
            </button>
        </div>

        <!-- New house: the server opens it once it is saved. -->
        <HouseNameForm
            v-if="adding"
            class="mt-3"
            :action="`/${sectionKey}/houses`"
            method="post"
            submit-label="Добавить"
            @done="adding = false"
            @cancel="adding = false"
        />

        <!-- The open house -->
        <div v-else-if="current" class="mt-3">
            <HouseNameForm
                v-if="renaming"
                :action="`/${sectionKey}/houses/${current.id}`"
                method="put"
                :initial="current.name"
                submit-label="Сохранить"
                @done="renaming = false"
                @cancel="renaming = false"
            />

            <div
                v-else-if="confirmingDelete"
                class="flex flex-wrap items-center gap-2 rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-900/20 dark:text-red-300"
            >
                <span class="min-w-0 flex-1">
                    Удалить дом «{{ current.name }}»? Его записи останутся в разделе — без дома.
                </span>
                <button
                    type="button"
                    class="rounded-md bg-red-600 px-3 py-1.5 font-medium text-white transition hover:bg-red-700"
                    @click="remove(current.id)"
                >
                    Удалить
                </button>
                <button
                    type="button"
                    class="rounded-md px-3 py-1.5 transition hover:bg-red-100 dark:hover:bg-red-900/40"
                    @click="confirmingDelete = false"
                >
                    Отмена
                </button>
            </div>

            <div v-else class="flex flex-wrap items-center gap-1">
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-800 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-200"
                    @click="renaming = true"
                >
                    <Icon name="pencil" :size="14" />
                    Переименовать
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm text-neutral-500 transition hover:bg-neutral-100 hover:text-red-600 dark:text-neutral-400 dark:hover:bg-neutral-800"
                    @click="confirmingDelete = true"
                >
                    <Icon name="trash" :size="14" />
                    Удалить дом
                </button>
            </div>
        </div>
    </div>
</template>
