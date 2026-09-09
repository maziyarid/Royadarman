<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        'private-opg' => [
            'driver' => 'local',
            'root' => env('ROYADARMAN_OPG_ROOT', storage_path('app/private/opg')),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],

        'opg-quarantine' => [
            'driver' => 'local',
            'root' => env('ROYADARMAN_OPG_QUARANTINE_ROOT', storage_path('app/private/opg-quarantine')),
            'serve' => false,
            'throw' => true,
            'report' => true,
        ],
    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],
];
