<?php

return [
    'intake_enabled' => filter_var(env('INTAKE_ENABLED', env('ROYADARMAN_ACCEPT_INTAKE', false)), FILTER_VALIDATE_BOOL),
    'phone_hash_key' => env('ROYADARMAN_PHONE_HASH_KEY'),
    'supported_locales' => ['fa', 'ar', 'en'],
    'display_timezone' => 'Asia/Tehran',
    'panel_demo_access' => filter_var(env('PANEL_DEMO_ACCESS', false), FILTER_VALIDATE_BOOL),

    'tehran_areas' => [
        'north',
        'central',
        'east',
        'west',
        'south',
    ],

    'tehran_neighborhoods' => [
        ['id' => 'tajrish', 'area' => 'north', 'fa' => 'تجریش', 'en' => 'Tajrish', 'ar' => 'تجريش'],
        ['id' => 'niavaran', 'area' => 'north', 'fa' => 'نیاوران', 'en' => 'Niavaran', 'ar' => 'نياوران'],
        ['id' => 'pasdaran', 'area' => 'north', 'fa' => 'پاسداران', 'en' => 'Pasdaran', 'ar' => 'باسداران'],
        ['id' => 'vanak', 'area' => 'north', 'fa' => 'ونک', 'en' => 'Vanak', 'ar' => 'فناك'],
        ['id' => 'jordan', 'area' => 'north', 'fa' => 'جردن', 'en' => 'Jordan', 'ar' => 'جردن'],
        ['id' => 'saadatabad', 'area' => 'west', 'fa' => 'سعادت‌آباد', 'en' => 'Saadatabad', 'ar' => 'سعادت آباد'],
        ['id' => 'shahrak-gharb', 'area' => 'west', 'fa' => 'شهرک غرب', 'en' => 'Shahrak-e Gharb', 'ar' => 'شهرك غرب'],
        ['id' => 'punak', 'area' => 'west', 'fa' => 'پونک', 'en' => 'Punak', 'ar' => 'بونك'],
        ['id' => 'sadeghieh', 'area' => 'west', 'fa' => 'صادقیه', 'en' => 'Sadeghieh', 'ar' => 'صادقية'],
        ['id' => 'valiasr', 'area' => 'central', 'fa' => 'ولیعصر', 'en' => 'Valiasr', 'ar' => 'ولي العصر'],
        ['id' => 'enghelab', 'area' => 'central', 'fa' => 'انقلاب', 'en' => 'Enghelab', 'ar' => 'انقلاب'],
        ['id' => 'ferdowsi', 'area' => 'central', 'fa' => 'فردوسی', 'en' => 'Ferdowsi', 'ar' => 'فردوسي'],
        ['id' => 'tehranpars', 'area' => 'east', 'fa' => 'تهرانپارس', 'en' => 'Tehranpars', 'ar' => 'طهران بارس'],
        ['id' => 'narmak', 'area' => 'east', 'fa' => 'نارمک', 'en' => 'Narmak', 'ar' => 'نارمك'],
        ['id' => 'naziabad', 'area' => 'south', 'fa' => 'نازی‌آباد', 'en' => 'Naziabad', 'ar' => 'نازي آباد'],
        ['id' => 'javadiyeh', 'area' => 'south', 'fa' => 'جوادیه', 'en' => 'Javadiyeh', 'ar' => 'جوادية'],
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
        'provider' => strtolower((string) env('ROYADARMAN_SMS_PROVIDER', 'tsms')),
        'callbacks_enabled' => filter_var(env('ROYADARMAN_SMS_CALLBACKS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'callback_secret' => env('ROYADARMAN_SMS_CALLBACK_SECRET'),
        // Legacy/generic HTTP adapter retained as an explicit fallback provider.
        'endpoint' => env('ROYADARMAN_SMS_ENDPOINT'),
        'token' => env('ROYADARMAN_SMS_TOKEN'),
        'tsms' => [
            'endpoint' => env('TSMS_API_URL', 'https://tsms.ir/url/tsmshttp.php'),
            'username' => env('TSMS_USERNAME'),
            'password' => env('TSMS_PASSWORD'),
            'from' => env('TSMS_FROM'),
        ],
    ],
    'referral' => [
        'grant_ttl_minutes' => env('ROYADARMAN_REFERRAL_GRANT_TTL_MINUTES'),
    ],
    'retention' => [
        'document_days' => env('ROYADARMAN_DOCUMENT_RETENTION_DAYS'),
    ],
    'cms' => [
        'media' => [
            'disk' => env('ROYADARMAN_CMS_DISK', 'public-cms'),
            'max_bytes' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_BYTES', 8 * 1024 * 1024),
            'max_edge' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_EDGE', 8000),
            'max_pixels' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_PIXELS', 40_000_000),
        ],
    ],
];
