<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Atheer ERP Report API
    |--------------------------------------------------------------------------
    |
    | The second-brain reads live business figures from the Atheer ERP's
    | read-only report API. Both apps are co-deployed, so this defaults to a
    | localhost address. The token is the shared BRAIN_API_TOKEN configured on
    | the Atheer side.
    |
    */

    'api_url' => rtrim((string) env('ATHEER_API_URL', 'http://127.0.0.1:8000'), '/'),

    'api_token' => env('ATHEER_API_TOKEN'),

    // How long (seconds) to cache Atheer responses. Avoids hammering the ERP
    // on repeated questions. Default 15 minutes.
    'cache_ttl' => (int) env('ATHEER_CACHE_TTL', 900),

];
