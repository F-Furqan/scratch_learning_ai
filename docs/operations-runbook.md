# Operations Runbook

The commands below assume the release symlink is `/var/www/scratch-learning/current`, PHP is `/usr/bin/php`, and the web process runs as `www-data`. Adjust those three values once if the host uses different paths.

## Required Production Environment

Use these production-safe values in addition to the database, mail, Paddle, and filesystem credentials:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://learn.example.com

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=150
QUEUE_FAILED_DRIVER=database-uuids
QUEUE_WORKER_TIMEOUT=120
QUEUE_WORKER_MAX_TIME=3600
QUEUE_HEARTBEAT_REQUIRED=true
QUEUE_HEARTBEAT_MAX_AGE_SECONDS=180

CACHE_STORE=database
SESSION_DRIVER=database
SESSION_ENCRYPT=true

LOG_CHANNEL=stack
LOG_STACK=daily,stderr
LOG_LEVEL=warning
LOG_DAILY_DAYS=14
LOG_EXTERNAL_ROTATION=false

MAIL_MAILER=smtp
OPERATIONS_ALERT_MAIL_TO=operations@example.com
BACKUP_FAILURE_MAIL_TO=operations@example.com
BACKUPS_ENABLED=true

PAYMENT_PROVIDER=paddle
PADDLE_ENVIRONMENT=live
PADDLE_API_KEY=live_api_key_from_paddle
PADDLE_WEBHOOK_SECRET=live_webhook_secret_from_paddle
```

Keep `DB_QUEUE_RETRY_AFTER` greater than the worker `--timeout`. The supplied values allow a killed worker to release a job only after its 120-second execution window has ended.

## Storage Permissions

Run these commands after creating a release and before production validation:

```bash
cd /var/www/scratch-learning/current
sudo chown -R deploy:www-data storage bootstrap/cache
sudo find storage bootstrap/cache -type d -exec chmod 2775 {} \;
sudo find storage bootstrap/cache -type f -exec chmod 0664 {} \;
sudo -u www-data test -r storage
sudo -u www-data test -w storage
sudo -u www-data test -w bootstrap/cache
php artisan storage:link
```

Do not make `storage` world-writable. Database backups remain on the private disk under `storage/app/private` unless a private managed disk is configured.

## Queue Worker

Install the supplied Supervisor definition:

```bash
sudo install -m 0644 deploy/supervisor/scratch-learning-worker.conf.example /etc/supervisor/conf.d/scratch-learning-worker.conf
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start 'scratch-learning-worker:*'
sudo supervisorctl status 'scratch-learning-worker:*'
```

The exact worker command is:

```bash
/usr/bin/php artisan queue:work database --queue=payments,monitoring,default --sleep=3 --tries=5 --timeout=120 --max-time=3600 --no-interaction
```

Supervisor restarts crashed workers automatically. `--max-time=3600` also replaces each long-running PHP process hourly, limiting memory growth. Worker logs rotate at 50 MB with ten retained files.

Gracefully restart workers after every deployment:

```bash
php artisan queue:restart
sudo supervisorctl status 'scratch-learning-worker:*'
```

Inspect and recover failed jobs with:

```bash
php artisan queue:failed
php artisan queue:retry <failed-job-uuid>
php artisan platform:monitor --no-alerts
```

The scheduler prunes failed-job records older than seven days. Export or investigate a failure before retrying or pruning it.

## Scheduler

Install the supplied system cron entry:

```bash
sudo install -m 0644 deploy/cron/scratch-learning /etc/cron.d/scratch-learning
sudo systemctl reload cron
php artisan schedule:list
php artisan schedule:run --no-interaction
```

Cron calls Laravel every minute. Laravel then dispatches the queue heartbeat, runs `platform:monitor`, publishes scheduled editorial content, aggregates ad reports, prunes old failed jobs, and creates the enabled daily database backup.

On distributions that name the service `crond`, replace `sudo systemctl reload cron` with `sudo systemctl reload crond`.

## Health And Alerts

Use `/up` for process liveness and `/health` for dependency readiness:

```bash
curl --fail --silent --show-error https://learn.example.com/up
curl --fail --silent --show-error https://learn.example.com/health
```

`/health` returns HTTP 503 if the database query fails, the queue backend is unavailable, the worker heartbeat is older than the configured limit, or the configured storage disk cannot complete a write/read/delete probe. Responses use `Cache-Control: no-store` and do not expose credentials or database names.

`platform:monitor` alerts on:

- unresolved rows in `failed_jobs`;
- Paddle events in `failed` state;
- Paddle events stuck in `accepted` or `processing`;
- missing, failed, or stale backups when backups are enabled.

Alerts go to `OPERATIONS_ALERT_MAIL_TO` and are deduplicated for `OPERATIONS_ALERT_COOLDOWN_MINUTES`. Backup creation and restore-verification failures also alert immediately. Laravel application and monitoring logs rotate daily according to `LOG_DAILY_DAYS`.

The Paddle dashboard webhook destination must be:

```text
https://learn.example.com/api/webhooks/paddle
```

After configuring it, send a Paddle sandbox test event in staging and confirm it reaches `processed` status before switching production credentials to live.

## Production Validation

Run the validator after environment configuration and permissions are in place:

```bash
php artisan config:clear
php artisan platform:validate-production
```

The command exits non-zero if production is unsafe. Deployment automation must stop on that exit code.

## Deployment Sequence

Run this sequence from the new release directory:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build:ssr
php artisan config:clear
php artisan platform:validate-production
php artisan platform:backup --verify
php artisan down --retry=60
php artisan migrate --force
php artisan optimize
php artisan storage:link
php artisan queue:restart
php artisan up
curl --fail --silent --show-error https://learn.example.com/up
curl --fail --silent --show-error https://learn.example.com/health
php artisan platform:monitor --no-alerts
```

Build assets in CI when the production host does not have Node.js, then deploy the generated `public/build` and `bootstrap/ssr` artifacts instead of running `npm ci` on the host.

## Rollback

If readiness fails after deployment:

```bash
php artisan down --retry=60
ln -sfn /var/www/scratch-learning/releases/<previous-release> /var/www/scratch-learning/current
cd /var/www/scratch-learning/current
php artisan optimize
php artisan queue:restart
php artisan up
curl --fail --silent --show-error https://learn.example.com/up
curl --fail --silent --show-error https://learn.example.com/health
```

Restore a database backup only when the release migration changed data incompatibly. Verify the selected snapshot in a disposable restore database before replacing production data.
