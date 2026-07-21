<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Application Hosts
    |--------------------------------------------------------------------------
    |
    | The public website, full web portal and mobile portal are presentation
    | surfaces of one Laravel application. They share authentication, domain
    | services, storage and the database.
    |
    */
    'hosts' => [
        'public' => env('PUBLIC_HOST', 'belovedcollege.test'),
        'web' => env('WEB_PORTAL_HOST', 'web.belovedcollege.test'),
        'app' => env('APP_PORTAL_HOST', 'app.belovedcollege.test'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme Contract
    |--------------------------------------------------------------------------
    |
    | New2 intentionally supports exactly two themes. Theme values stored in
    | the database must resolve to one of these semantic identifiers.
    |
    */
    'themes' => [
        'classic',
        'dark',
    ],

    'default_theme' => env('DEFAULT_THEME', 'classic'),

    'allow_user_theme_selection' => env('ALLOW_USER_THEME_SELECTION', true),

    /*
    |--------------------------------------------------------------------------
    | Legacy Compatibility
    |--------------------------------------------------------------------------
    |
    | Compatibility mode remains enabled while the previous database and file
    | paths are being mapped. It must be disabled only after parity sign-off.
    |
    */
    'legacy_compatibility' => env('LEGACY_COMPATIBILITY', true),
];
