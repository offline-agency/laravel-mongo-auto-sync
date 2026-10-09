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
    // null = the application's default queue connection / queue
    'queue_connection' => env('MONGO_AUTO_SYNC_QUEUE_CONNECTION'),
    'queue_name' => env('MONGO_AUTO_SYNC_QUEUE_NAME'),
    // true = target collections are synced by a queued job (eventual consistency, a worker is required)
    'use_background_sync' => (bool) env('MONGO_AUTO_SYNC_BACKGROUND_SYNC', false),
    'logging' => (bool) env('MONGO_AUTO_SYNC_LOGGING', false),
];
