<?php

namespace Tests\Feature;

use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use App\Jobs\QueueHeartbeatJob;
use App\Models\DatabaseBackup;
use App\Models\PaymentWebhookEvent;
use App\Notifications\OperationalAlertNotification;
use App\Services\Operations\OperationalMonitor;
use App\Services\Operations\ProductionEnvironmentValidator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Cache::flush();
        config()->set('operations.health.storage_disk', 'local');
        config()->set('operations.health.queue_heartbeat_required', false);
        config()->set('operations.backups.enabled', false);
    }

    public function test_readiness_endpoint_checks_application_database_queue_and_storage(): void
    {
        $this->getJson(route('health'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('checks.application.status', 'healthy')
            ->assertJsonPath('checks.database.status', 'healthy')
            ->assertJsonPath('checks.queue.status', 'healthy')
            ->assertJsonPath('checks.storage.status', 'healthy')
            ->assertJsonPath('checks.database.metadata.connection', 'mysql')
            ->assertJsonPath('checks.storage.metadata.disk', 'local');
    }

    public function test_queue_heartbeat_controls_readiness_when_required(): void
    {
        config()->set('operations.health.queue_heartbeat_required', true);
        config()->set('operations.health.queue_heartbeat_max_age_seconds', 180);

        $this->getJson(route('health'))
            ->assertServiceUnavailable()
            ->assertJsonPath('checks.queue.status', 'unhealthy');

        (new QueueHeartbeatJob)->handle();

        $this->getJson(route('health'))
            ->assertOk()
            ->assertJsonPath('checks.queue.status', 'healthy');

        Cache::put(
            (string) config('operations.health.queue_heartbeat_key'),
            now()->subMinutes(5)->timestamp,
        );

        $this->getJson(route('health'))
            ->assertServiceUnavailable()
            ->assertJsonPath('checks.queue.status', 'unhealthy');
    }

    public function test_scheduler_runs_heartbeat_and_monitor_each_minute(): void
    {
        $events = collect(app(Schedule::class)->events())->keyBy('description');
        $heartbeat = $events->get('operations.queue-heartbeat');
        $monitor = $events->get('platform.monitor');
        $prune = $events->get('queue.prune-failed');

        $this->assertNotNull($heartbeat);
        $this->assertNotNull($monitor);
        $this->assertNotNull($prune);
        $this->assertSame('* * * * *', $heartbeat->expression);
        $this->assertSame('* * * * *', $monitor->expression);
        $this->assertSame('0 3 * * *', $prune->expression);
        $this->assertTrue($heartbeat->withoutOverlapping);
        $this->assertTrue($monitor->withoutOverlapping);
        $this->assertTrue($monitor->onOneServer);
    }

    public function test_operational_monitor_alerts_for_failed_jobs_and_paddle_webhooks(): void
    {
        Notification::fake();
        config()->set('operations.alerts.mail_to', 'operations@example.com');
        config()->set('operations.monitoring.failed_jobs_threshold', 0);
        config()->set('operations.monitoring.paddle_stale_minutes', 10);

        DB::table('failed_jobs')->insert([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Focused monitoring failure.',
            'failed_at' => now(),
        ]);
        PaymentWebhookEvent::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_phase5_failed',
            'event_type' => 'transaction.completed',
            'status' => 'failed',
            'payload' => ['event_id' => 'evt_phase5_failed'],
            'queued_at' => now(),
        ]);
        PaymentWebhookEvent::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_phase5_stale',
            'event_type' => 'transaction.completed',
            'status' => 'accepted',
            'payload' => ['event_id' => 'evt_phase5_stale'],
            'queued_at' => now()->subMinutes(20),
        ]);

        $issues = app(OperationalMonitor::class)->run();

        $this->assertSame([
            'queue.failed_jobs',
            'paddle.webhooks_failed',
            'paddle.webhooks_stale',
        ], collect($issues)->pluck('key')->all());
        Notification::assertSentOnDemand(OperationalAlertNotification::class);
    }

    public function test_operational_monitor_reports_the_latest_failed_backup(): void
    {
        Notification::fake();
        config()->set('operations.alerts.mail_to', 'operations@example.com');
        config()->set('operations.backups.enabled', true);

        DatabaseBackup::query()->create([
            'driver' => 'mysql',
            'connection_name' => 'mysql',
            'database_name' => 'scratch-test',
            'status' => DatabaseBackupStatus::Failed,
            'verification_status' => BackupVerificationStatus::NotRequested,
            'failure_reason' => 'Focused backup failure.',
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $issues = app(OperationalMonitor::class)->run();

        $this->assertContains('backup.latest_failed', collect($issues)->pluck('key')->all());
        Notification::assertSentOnDemand(OperationalAlertNotification::class);
    }

    public function test_production_validator_rejects_defaults_and_accepts_hardened_configuration(): void
    {
        $validator = app(ProductionEnvironmentValidator::class);

        $this->assertFalse($validator->passes($validator->validate()));

        config()->set([
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://learn.example.com',
            'app.key' => 'base64:'.base64_encode(Str::random(32)),
            'database.connections.mysql.database' => 'scratch_learning_production',
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 150,
            'queue.failed.driver' => 'database-uuids',
            'operations.queue.worker_timeout_seconds' => 120,
            'operations.health.queue_heartbeat_required' => true,
            'cache.default' => 'database',
            'session.driver' => 'database',
            'session.encrypt' => true,
            'logging.default' => 'stack',
            'logging.channels.stack.channels' => ['daily', 'stderr'],
            'operations.alerts.mail_to' => 'operations@example.com',
            'mail.default' => 'smtp',
            'operations.backups.enabled' => true,
            'operations.backups.failure_mail_to' => 'operations@example.com',
            'payments.provider' => 'paddle',
            'payments.paddle.environment' => 'live',
            'payments.paddle.api_key' => 'pdl_live_phase5',
            'payments.paddle.webhook_secret' => 'pdl_live_webhook_phase5',
        ]);

        $results = $validator->validate();

        $this->assertTrue($validator->passes($results), collect($results)
            ->reject(fn (array $result): bool => $result['passed'])
            ->pluck('name')
            ->implode(', '));
    }

    public function test_worker_and_scheduler_process_definitions_include_restart_and_rotation_guards(): void
    {
        $supervisor = File::get(base_path('deploy/supervisor/scratch-learning-worker.conf.example'));
        $cron = File::get(base_path('deploy/cron/scratch-learning'));
        $environment = File::get(base_path('.env.example'));
        $runbook = File::get(base_path('docs/operations-runbook.md'));

        $this->assertStringContainsString('autorestart=true', $supervisor);
        $this->assertStringContainsString('--queue=payments,monitoring,default', $supervisor);
        $this->assertStringContainsString('--timeout=120', $supervisor);
        $this->assertStringContainsString('--max-time=3600', $supervisor);
        $this->assertStringContainsString('stdout_logfile_maxbytes=50MB', $supervisor);
        $this->assertStringContainsString('* * * * * www-data', $cron);
        $this->assertStringContainsString('artisan schedule:run', $cron);
        $this->assertStringContainsString('LOG_STACK=daily,stderr', $environment);
        $this->assertSame('daily', config('logging.channels.daily.driver'));
        $this->assertSame(14, config('logging.channels.daily.days'));
        $this->assertStringContainsString('php artisan platform:validate-production', $runbook);
        $this->assertStringContainsString('php artisan platform:backup --verify', $runbook);
        $this->assertStringContainsString('php artisan migrate --force', $runbook);
        $this->assertStringContainsString('php artisan queue:restart', $runbook);
        $this->assertStringContainsString('ln -sfn /var/www/scratch-learning/releases/<previous-release>', $runbook);
        $this->assertStringContainsString('curl --fail --silent --show-error https://learn.example.com/health', $runbook);
    }
}
