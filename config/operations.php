<?php

use App\Services\Operations\UnsupportedManagedSnapshotProvider;

return [
    'backups' => [
        'enabled' => (bool) env('BACKUPS_ENABLED', false),
        'driver' => env('BACKUP_DRIVER', 'auto'),
        'connection' => env('BACKUP_CONNECTION'),
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'backups/database'),
        'retention_days' => (int) env('BACKUP_RETENTION_DAYS', 14),
        'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 900),
        'verification_database' => env('BACKUP_VERIFY_DATABASE', 'scratch-restore-test'),
        'failure_mail_to' => env('BACKUP_FAILURE_MAIL_TO'),
        'mysql' => [
            'dump_binary' => env('MYSQLDUMP_BINARY', 'mysqldump'),
            'client_binary' => env('MYSQL_CLIENT_BINARY', 'mysql'),
        ],
        'managed' => [
            'provider' => env('BACKUP_MANAGED_PROVIDER', UnsupportedManagedSnapshotProvider::class),
        ],
    ],

    'health' => [
        'storage_disk' => env('HEALTH_STORAGE_DISK', env('FILESYSTEM_DISK', 'local')),
        'queue_heartbeat_key' => env('QUEUE_HEARTBEAT_KEY', 'operations.queue_heartbeat_at'),
        'queue_heartbeat_required' => (bool) env('QUEUE_HEARTBEAT_REQUIRED', true),
        'queue_heartbeat_max_age_seconds' => (int) env('QUEUE_HEARTBEAT_MAX_AGE_SECONDS', 180),
        'scheduler_heartbeat_max_age_seconds' => (int) env('SCHEDULER_HEARTBEAT_MAX_AGE_SECONDS', 180),
    ],

    'alerts' => [
        'mail_to' => env('OPERATIONS_ALERT_MAIL_TO'),
        'cooldown_minutes' => (int) env('OPERATIONS_ALERT_COOLDOWN_MINUTES', 15),
    ],

    'queue' => [
        'worker_timeout_seconds' => (int) env('QUEUE_WORKER_TIMEOUT', 120),
        'worker_max_time_seconds' => (int) env('QUEUE_WORKER_MAX_TIME', 3600),
    ],

    'logging' => [
        'external_rotation' => (bool) env('LOG_EXTERNAL_ROTATION', false),
        'database_retention_days' => (int) env('LOG_DATABASE_RETENTION_DAYS', 14),
        'health_retention_days' => (int) env('HEALTH_HISTORY_RETENTION_DAYS', 14),
        'scheduler_retention_days' => (int) env('SCHEDULER_HISTORY_RETENTION_DAYS', 14),
    ],

    'monitoring' => [
        'slow_request_ms' => (int) env('MONITORING_SLOW_REQUEST_MS', 1000),
        'slow_query_ms' => (int) env('MONITORING_SLOW_QUERY_MS', 250),
        'public_listing_max_ms' => (int) env('LOAD_CHECK_PUBLIC_LISTING_MAX_MS', 1500),
        'admin_table_max_ms' => (int) env('LOAD_CHECK_ADMIN_TABLE_MAX_MS', 2000),
        'failed_jobs_threshold' => (int) env('MONITORING_FAILED_JOBS_THRESHOLD', 0),
        'paddle_failure_window_minutes' => (int) env('MONITORING_PADDLE_FAILURE_WINDOW_MINUTES', 60),
        'paddle_stale_minutes' => (int) env('MONITORING_PADDLE_STALE_MINUTES', 10),
        'backup_max_age_hours' => (int) env('MONITORING_BACKUP_MAX_AGE_HOURS', 26),
    ],
];
