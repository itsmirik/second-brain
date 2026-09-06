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
            'description' => 'Instagram leads, deliveries, reconciliation and finance.',
            'icon' => 'store',
            'driver' => 'atheer',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'personal',
            'label' => 'Personal',
            'description' => 'Notes, ideas and reminders.',
            'icon' => 'user',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
        [
            'key' => 'home-business',
            'label' => 'Home business',
            'description' => 'Side ventures run from home.',
            'icon' => 'home',
            'driver' => 'entries',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'health',
            'label' => 'Health',
            'description' => 'Health journal and metrics.',
            'icon' => 'heart',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
        [
            'key' => 'budget',
            'label' => 'Budget',
            'description' => 'Household spending and income.',
            'icon' => 'wallet',
            'driver' => 'entries',
            'money' => true,
            'status' => 'live',
        ],
        [
            'key' => 'family',
            'label' => 'Family',
            'description' => 'Family matters and charity giving.',
            'icon' => 'users',
            'driver' => 'entries',
            'money' => false,
            'status' => 'live',
        ],
    ],
];
