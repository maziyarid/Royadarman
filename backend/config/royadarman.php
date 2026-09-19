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

    // Public neighbourhood centroids for discovery origin only — not patient GPS.
    'tehran_neighborhoods' => [
        ['id' => 'tajrish', 'area' => 'north', 'lat' => 35.8044, 'lng' => 51.4256, 'fa' => 'تجریش', 'en' => 'Tajrish', 'ar' => 'تجريش'],
        ['id' => 'niavaran', 'area' => 'north', 'lat' => 35.8167, 'lng' => 51.4722, 'fa' => 'نیاوران', 'en' => 'Niavaran', 'ar' => 'نياوران'],
        ['id' => 'pasdaran', 'area' => 'north', 'lat' => 35.7720, 'lng' => 51.4670, 'fa' => 'پاسداران', 'en' => 'Pasdaran', 'ar' => 'باسداران'],
        ['id' => 'vanak', 'area' => 'north', 'lat' => 35.7572, 'lng' => 51.4103, 'fa' => 'ونک', 'en' => 'Vanak', 'ar' => 'فناك'],
        ['id' => 'jordan', 'area' => 'north', 'lat' => 35.7725, 'lng' => 51.4183, 'fa' => 'جردن', 'en' => 'Jordan', 'ar' => 'جردن'],
        ['id' => 'saadatabad', 'area' => 'west', 'lat' => 35.7808, 'lng' => 51.3756, 'fa' => 'سعادت‌آباد', 'en' => 'Saadatabad', 'ar' => 'سعادت آباد'],
        ['id' => 'shahrak-gharb', 'area' => 'west', 'lat' => 35.7575, 'lng' => 51.3753, 'fa' => 'شهرک غرب', 'en' => 'Shahrak-e Gharb', 'ar' => 'شهرك غرب'],
        ['id' => 'punak', 'area' => 'west', 'lat' => 35.7628, 'lng' => 51.3317, 'fa' => 'پونک', 'en' => 'Punak', 'ar' => 'بونك'],
        ['id' => 'sadeghieh', 'area' => 'west', 'lat' => 35.7178, 'lng' => 51.3381, 'fa' => 'صادقیه', 'en' => 'Sadeghieh', 'ar' => 'صادقية'],
        ['id' => 'valiasr', 'area' => 'central', 'lat' => 35.7060, 'lng' => 51.4055, 'fa' => 'ولیعصر', 'en' => 'Valiasr', 'ar' => 'ولي العصر'],
        ['id' => 'enghelab', 'area' => 'central', 'lat' => 35.7011, 'lng' => 51.3950, 'fa' => 'انقلاب', 'en' => 'Enghelab', 'ar' => 'انقلاب'],
        ['id' => 'ferdowsi', 'area' => 'central', 'lat' => 35.6925, 'lng' => 51.4189, 'fa' => 'فردوسی', 'en' => 'Ferdowsi', 'ar' => 'فردوسي'],
        ['id' => 'tehranpars', 'area' => 'east', 'lat' => 35.7414, 'lng' => 51.5311, 'fa' => 'تهرانپارس', 'en' => 'Tehranpars', 'ar' => 'طهران بارس'],
        ['id' => 'narmak', 'area' => 'east', 'lat' => 35.7286, 'lng' => 51.5142, 'fa' => 'نارمک', 'en' => 'Narmak', 'ar' => 'نارمك'],
        ['id' => 'naziabad', 'area' => 'south', 'lat' => 35.6453, 'lng' => 51.3961, 'fa' => 'نازی‌آباد', 'en' => 'Naziabad', 'ar' => 'نازي آباد'],
        ['id' => 'javadiyeh', 'area' => 'south', 'lat' => 35.6564, 'lng' => 51.3864, 'fa' => 'جوادیه', 'en' => 'Javadiyeh', 'ar' => 'جوادية'],
    ],

    'discovery' => [
        // Mean Earth radius (km). Application Haversine; no PostGIS.
        'earth_radius_km' => 6371.0,
        'km_per_degree_latitude' => 111.32,
        'default_radius_km' => 15.0,
        'max_radius_km' => 40.0,
        'location_freshness_days' => 180,
        'capability_freshness_days' => 180,
    ],

    'neshan' => [
        // Client MapLibre SDK key. Empty fails closed: list/directions still work.
        // Domain-restrict the key in the Neshan panel. Never commit a real value.
        'map_api_key' => env('NESHAN_MAP_API_KEY'),
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
            // Keep the default lockstep with infra/host.env.example (CLAMSCAN).
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
        'grant_ttl_minutes' => ($ttl = env('ROYADARMAN_REFERRAL_GRANT_TTL_MINUTES')) === '' ? null : $ttl,
        // Unanswered-proposal SLA for coordinator surfacing. Default 1440 minutes.
        // Blank/missing env uses the default; grant TTL remains the intake gate.
        'proposal_sla_minutes' => ($sla = env('ROYADARMAN_REFERRAL_PROPOSAL_SLA_MINUTES')) === '' || $sla === null ? 1440 : $sla,
    ],
    'retention' => [
        // `.env.example` used to assign FOO= which Laravel env() reads as "" not null.
        // Blank must stay fail-closed: no invented retention duration.
        'document_days' => ($days = env('ROYADARMAN_DOCUMENT_RETENTION_DAYS')) === '' ? null : $days,
    ],
    'cms' => [
        'media' => [
            'disk' => env('ROYADARMAN_CMS_DISK', 'public-cms'),
            'max_bytes' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_BYTES', 8 * 1024 * 1024),
            'max_edge' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_EDGE', 8000),
            'max_pixels' => (int) env('ROYADARMAN_CMS_MEDIA_MAX_PIXELS', 40_000_000),
        ],
    ],
    'queue' => [
        // Documented worker --timeout. Keep lockstep with infra/queue-timing.conf.
        // Preflight compares this to config('queue.connections.database.retry_after')
        // and does not inspect systemd.
        'worker_timeout_seconds' => (int) env('ROYADARMAN_QUEUE_WORKER_TIMEOUT', 85),
        'retry_after_min_margin_seconds' => 5,
    ],
];
