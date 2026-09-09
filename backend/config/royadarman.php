<?php

return [
    'intake_enabled' => filter_var(env('INTAKE_ENABLED', env('ROYADARMAN_ACCEPT_INTAKE', false)), FILTER_VALIDATE_BOOL),
    'phone_hash_key' => env('ROYADARMAN_PHONE_HASH_KEY', env('APP_KEY')),
    'supported_locales' => ['fa', 'ar', 'en'],
    'display_timezone' => 'Asia/Tehran',

    'tehran_areas' => [
        'north',
        'central',
        'east',
        'west',
        'south',
    ],

    'opg' => [
        'disk' => env('ROYADARMAN_OPG_DISK', 'private-opg'),
        'quarantine_disk' => env('ROYADARMAN_OPG_QUARANTINE_DISK', 'opg-quarantine'),
        'max_kilobytes' => (int) env('ROYADARMAN_OPG_MAX_KB', 20 * 1024),
        'max_image_pixels' => (int) env('ROYADARMAN_OPG_MAX_PIXELS', 60_000_000),
        'extensions' => ['jpg', 'jpeg', 'png'],
        'mime_types' => ['image/jpeg', 'image/png'],
        'scanner' => [
            'enabled' => (bool) env('ROYADARMAN_OPG_SCANNER_ENABLED', false),
            'command' => env('ROYADARMAN_OPG_SCANNER_COMMAND', '/usr/bin/clamscan'),
            'timeout_seconds' => (int) env('ROYADARMAN_OPG_SCANNER_TIMEOUT', 60),
        ],
    ],
    'sms' => [
        'endpoint' => env('ROYADARMAN_SMS_ENDPOINT'),
        'token' => env('ROYADARMAN_SMS_TOKEN'),
        'callback_secret' => env('ROYADARMAN_SMS_CALLBACK_SECRET'),
    ],
    'retention' => [
        'document_days' => env('ROYADARMAN_DOCUMENT_RETENTION_DAYS'),
    ],
];
