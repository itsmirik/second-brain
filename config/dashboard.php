<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Dashboard Sections
|--------------------------------------------------------------------------
|
| The owner's life is split into sections. Each is a self-contained area of
| the dashboard.
|
|   driver  — "atheer" reads the external ERP report API; "entries" is a
|             generic log backed by the entries table (SectionController).
|   money   — whether this section emphasises the amount field (totals).
|   status  — "live" sections have a route + UI; "planned" render nothing yet.
|
*/

return [
    /*
    | The single owner. Entries logged through the Telegram bot (which has no
    | authenticated session) are attributed to this user. Falls back to the
    | most recently created user when unset. The web chat always uses the
    | logged-in user instead.
    */
    'owner_email' => env('BRAIN_OWNER_EMAIL'),

    'sections' => [
        [
            'key' => 'atheer',
            'label' => 'Atheer',
            'description' => 'Лиды из Instagram, доставки, сверка и финансы.',
            'icon' => 'store',
            'driver' => 'atheer',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'personal',
            'label' => 'Личное',
            'description' => 'Заметки, идеи и напоминания.',
            'icon' => 'user',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
        [
            'key' => 'home-business',
            'label' => 'Домашний бизнес',
            'description' => 'Побочные проекты, которые ведутся из дома.',
            'icon' => 'home',
            'driver' => 'entries',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'health',
            'label' => 'Здоровье',
            'description' => 'Дневник здоровья и показатели.',
            'icon' => 'heart',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
        [
            'key' => 'budget',
            'label' => 'Бюджет',
            'description' => 'Домашние расходы и доходы.',
            'icon' => 'wallet',
            'driver' => 'entries',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'family',
            'label' => 'Семья',
            'description' => 'Семейные дела и быт.',
            'icon' => 'users',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
        [
            'key' => 'charity',
            'label' => 'Садака',
            'description' => 'Садака: месячная сумма к выплате и что уже отдано.',
            'icon' => 'gift',
            'driver' => 'entries',
            'money' => true,
            'status' => 'live',
        ],
    ],
];
