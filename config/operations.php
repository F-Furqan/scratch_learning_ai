<?php

return [
    'backups' => [
        'enabled' => (bool) env('BACKUPS_ENABLED', true),
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups/database'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
    ],

    'monitoring' => [
        'slow_request_ms' => (int) env('MONITORING_SLOW_REQUEST_MS', 1000),
        'slow_query_ms' => (int) env('MONITORING_SLOW_QUERY_MS', 250),
        'public_listing_max_ms' => (int) env('LOAD_CHECK_PUBLIC_LISTING_MAX_MS', 1500),
        'admin_table_max_ms' => (int) env('LOAD_CHECK_ADMIN_TABLE_MAX_MS', 2000),
    ],
];
