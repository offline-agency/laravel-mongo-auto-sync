<?php

return [
    'model_path' => app_path().'/Models',
    'model_namespace' => 'App\Models',
    'other_models' => [
        'user' => [
            'model_path' => app_path(),
            'model_namespace' => 'App',
        ],
    ],
    'fb_id' => env('FB_ID', ''),
    'queue_connection' => env('MONGO_AUTO_SYNC_QUEUE_CONNECTION', 'database'),
    'queue_name' => env('MONGO_AUTO_SYNC_QUEUE_NAME', 'mongo_auto_sync'),
    'use_background_sync' => env('MONGO_AUTO_SYNC_BACKGROUND_SYNC', true),
];
