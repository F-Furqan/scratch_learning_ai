# Launch Hardening Runbook

See [Operations Runbook](operations-runbook.md) for the exact Supervisor, cron, storage permission, validation, deployment, health-check, and rollback commands.

## Release Checklist

- Run `php artisan migrate --force` after a verified backup.
- Run `composer lint:check`, `composer types:check`, `php artisan test`, `npm run format:check`, `npm run lint:check`, `npm run types:check`, and `npm run build:ssr`.
- Confirm `QUEUE_CONNECTION=database` or a managed queue backend, then run at least one queue worker.
- Confirm scheduler is active with `php artisan schedule:work` in local/staging or cron/systemd in production.
- Configure `PADDLE_WEBHOOK_SECRET` before enabling Paddle webhook traffic.
- Configure centralized logs by setting `LOG_CHANNEL=stack` and `LOG_STACK=daily,stderr` or the production provider channel.

## Backups

- Backups are disabled by default. Keep `BACKUPS_ENABLED=false` until `php artisan platform:backup --dry-run` resolves the intended connection, private disk, path, and driver.
- Local SQLite and MySQL installs can run `php artisan platform:backup`; MySQL requires the `mysqldump` and `mysql` client binaries.
- Run `php artisan platform:backup --verify` before enabling the schedule. For MySQL this restores into `BACKUP_VERIFY_DATABASE`, which must be separate from the source and end in `restore-test`, then removes that disposable database.
- MySQL credentials are passed to child processes through their environment and are never included in command arguments or console output. Dumps use a single transaction and include routines, triggers, and events.
- Backup files are gzip-compressed, SHA-256 checksummed, and stored on `BACKUP_DISK`. The disk must be private and must not resolve inside `public/`.
- Set `BACKUP_FAILURE_MAIL_TO` to one or more comma-separated operations addresses. Failures are also written to the monitoring log and `audit_logs` when the database remains reachable.
- Production providers can bind their own `ManagedSnapshotProvider` implementation and set `BACKUP_DRIVER=managed` plus `BACKUP_MANAGED_PROVIDER` to that class.
- After a successful manual restore check, set `BACKUPS_ENABLED=true` and choose `BACKUP_RETENTION_DAYS` according to the recovery and compliance policy.

## Queues And Scheduler

- Required scheduled tasks:
  - `operations.queue-heartbeat` every minute.
  - `platform.monitor` every minute.
  - `ads.aggregate-reports` hourly.
  - `queue.prune-failed` daily at 03:00.
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
