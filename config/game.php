<?php

return [
    // First public release: start with 25 fully tested levels.
    // Raise this value only when the next level batch is designed and verified.
    'initial_level_count' => (int) env('INITIAL_LEVEL_COUNT', 25),

    // Admin credentials are supplied through the local environment, never
    // committed to source control. Existing administrators are preserved when
    // these values are not set on an already-installed instance.
    'admin_name' => env('ADMIN_NAME', 'Game Administrator'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // The simulated reward endpoint is available only outside production.
    // There is deliberately no environment override: a production deployment
    // must use a provider-verified callback before granting ad rewards.
    'rewarded_ads' => [
        'allow_development_mock' => ! in_array(env('APP_ENV', 'production'), ['production', 'prod'], true),
    ],
];
