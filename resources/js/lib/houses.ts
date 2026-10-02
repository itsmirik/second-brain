import type { HouseFilter } from '@/types';
import { router } from '@inertiajs/vue3';

// Opens one house of a section split by house, the entries filed under no
// house ('none'), or all of them (null). State is preserved, so text typed
// into the entry form survives switching to the house it belongs to.
export function showHouse(sectionKey: string, house: HouseFilter): void {
    router.get(`/${sectionKey}`, house === null ? {} : { house }, {
        preserveState: true,
    });
}
