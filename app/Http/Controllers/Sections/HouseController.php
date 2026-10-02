<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sections;

use App\Http\Controllers\Controller;
use App\Models\House;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The owner's houses, managed from the section they split (home business):
 * add one, rename it, delete it. Deleting a house never deletes money — its
 * entries stay in the section, filed under no house.
 */
class HouseController extends Controller
{
    public function store(Request $request, string $section): RedirectResponse
    {
        $userId = (int) $request->user()->getAuthIdentifier();

        $house = House::query()->create([
            'user_id' => $userId,
            'name' => $this->validatedName($request, $userId),
        ]);

        // Open the new house straight away.
        return to_route("sections.{$section}", ['house' => $house->id]);
    }

    public function update(Request $request, House $house): RedirectResponse
    {
        $this->ensureOwner($request, $house);

        $house->update(['name' => $this->validatedName($request, $house->user_id, $house)]);

        return back();
    }

    // Route defaults arrive after the path parameters, so $section comes last.
    public function destroy(Request $request, House $house, string $section): RedirectResponse
    {
        $this->ensureOwner($request, $house);

        DB::transaction(static function () use ($house): void {
            // The foreign key nulls these as well; doing it here keeps the
            // entries even where foreign keys are not enforced.
            $house->entries()->update(['house_id' => null]);
            $house->delete();
        });

        return to_route("sections.{$section}");
    }

    private function ensureOwner(Request $request, House $house): void
    {
        abort_unless(
            (int) $house->user_id === (int) $request->user()->getAuthIdentifier(),
            403,
        );
    }

    /**
     * The submitted name, whitespace-normalized and unique among the owner's
     * houses ignoring case. $current is the house being renamed, which may
     * keep its own name (or change only its case).
     */
    private function validatedName(Request $request, int $userId, ?House $current = null): string
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:'.House::NAME_MAX,
                static function (string $attribute, mixed $value, Closure $fail) use ($userId, $current): void {
                    $existing = House::named($userId, (string) $value);

                    if ($existing !== null && $existing->id !== $current?->id) {
                        $fail('Дом с таким названием уже есть.');
                    }
                },
            ],
        ]);

        return House::normalizeName((string) $validated['name']);
    }
}
