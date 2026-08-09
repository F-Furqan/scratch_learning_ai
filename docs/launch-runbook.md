# Launch Hardening Runbook

## Release Checklist

- Run `php artisan migrate --force` after a verified backup.
- Run `composer lint:check`, `composer types:check`, `php artisan test`, `npm run format:check`, `npm run lint:check`, `npm run types:check`, and `npm run build:ssr`.
- Confirm `QUEUE_CONNECTION=database` or a managed queue backend, then run at least one queue worker.
- Confirm scheduler is active with `php artisan schedule:work` in local/staging or cron/systemd in production.
- Configure `PADDLE_WEBHOOK_SECRET` before enabling Paddle webhook traffic.
- Configure centralized logs by setting `LOG_CHANNEL=stack` and `LOG_STACK=daily,stderr` or the production provider channel.

## Backups

- Local SQLite installs can run `php artisan platform:backup`.
- Production databases should use managed snapshots or engine-native dumps before each deploy.
- Keep `BACKUPS_ENABLED=true`, choose a durable `BACKUP_DISK`, and set `BACKUP_RETENTION_DAYS` to the compliance window.
- Verify restoration in staging before launch, not during an incident.

## Queues And Scheduler

- Required scheduled tasks:
  - `ads.aggregate-reports` hourly.
  - `platform.backup` daily at 02:10 when backups are enabled.
- Queue workers should run with a process monitor and restart on deploy.
- Failed jobs must be reviewed from `failed_jobs` before each release.

## Logging And Monitoring

- Watch HTTP 5xx rate, queue depth, failed jobs, database backup freshness, slow public listings, and slow admin tables.
- Use `MONITORING_SLOW_REQUEST_MS`, `MONITORING_SLOW_QUERY_MS`, `LOAD_CHECK_PUBLIC_LISTING_MAX_MS`, and `LOAD_CHECK_ADMIN_TABLE_MAX_MS` to keep environment-specific thresholds explicit.
- Alert when a Paddle webhook returns 401/503 or when no accepted payment webhook events arrive during a known campaign window.

## Rollback Plan

- Keep the previous application build artifact and environment file available.
- Put the app in maintenance mode with `php artisan down --render="errors::503"` if data integrity is at risk.
- Restore the previous build, run `php artisan config:cache`, `php artisan route:cache`, and restart workers.
- Restore the latest verified database snapshot only when the migration changed data destructively.
- Bring the app back with `php artisan up`, then run smoke checks for home, course listing, blog listing, admin dashboard, API course listing, and Paddle webhook signature rejection.
