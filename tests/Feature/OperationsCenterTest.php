<?php

namespace Tests\Feature;

use App\Enums\BackupVerificationStatus;
use App\Enums\DatabaseBackupStatus;
use App\Enums\RoleName;
use App\Jobs\VerifyDatabaseBackupJob;
use App\Models\ApplicationLogEntry;
use App\Models\ApprovalHistory;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\DatabaseBackup;
use App\Models\HealthCheckRun;
use App\Models\OperationalAlert;
use App\Models\SchedulerHeartbeat;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Registry\AdminResourceRegistry;
use Modules\Admin\Services\OperationsCenterAdminService;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OperationsCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
        Storage::fake('local');
        Cache::flush();
        config()->set('operations.health.storage_disk', 'local');
        config()->set('operations.health.queue_heartbeat_required', false);
        config()->set('operations.backups.enabled', false);
    }

    public function test_operations_resources_are_registered_routed_and_visible_in_navigation(): void
    {
        $registry = app(AdminResourceRegistry::class);

        $this->assertCount(104, $registry->all());
        $this->assertEqualsCanonicalizing(
            OperationsCenterAdminService::RESOURCES,
            $registry->all()->keys()->intersect(OperationsCenterAdminService::RESOURCES)->values()->all(),
        );

        foreach (OperationsCenterAdminService::RESOURCES as $resource) {
            $this->assertNotNull(route('admin.'.$registry->get($resource)->routeName.'.index', absolute: false));
        }

        $this->actingAs($this->admin)
            ->get(route('admin.operations-center.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/operations/Center')
                ->where('snapshot.status', 'attention')
                ->has('snapshot.cards', 6)
                ->where('capabilities.health', true)
                ->where('adminNavigation.6.items.0.title', 'Operations Center'));
    }

    public function test_backup_history_is_filtered_paginated_exportable_and_immutable(): void
    {
        DatabaseBackup::query()->create([
            'driver' => 'mysql',
            'connection_name' => 'mysql',
            'database_name' => 'scratch-test',
            'status' => DatabaseBackupStatus::Succeeded,
            'verification_status' => BackupVerificationStatus::Verified,
            'started_at' => now()->subMinute(),
            'completed_at' => now(),
            'verified_at' => now(),
            'bytes' => 2048,
            'checksum' => str_repeat('a', 64),
        ]);
        DatabaseBackup::query()->create([
            'driver' => 'managed',
            'connection_name' => 'mysql',
            'database_name' => 'other-test',
            'status' => DatabaseBackupStatus::Failed,
            'verification_status' => BackupVerificationStatus::NotRequested,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.operations.backups.index', [
                'search' => 'scratch-test',
                'status' => 'succeeded',
                'category' => 'mysql',
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resource', 'database_backups')
                ->has('rows.data', 1)
                ->where('rows.data.0.database', 'scratch-test')
                ->where('rows.data.0.verification', 'verified')
                ->where('canCreate', false)
                ->where('canEdit', false)
                ->where('canDelete', false)
                ->where('bulkActions.0.value', 'verify')
                ->where('exportUrl', route('admin.operational-records.export', [
                    'resource' => 'database_backups',
                    'search' => 'scratch-test',
                    'status' => 'succeeded',
                    'category' => 'mysql',
                ], false)));

        $this->actingAs($this->admin)
            ->post(route('admin.operations.backups.store'), [])
            ->assertStatus(405);
    }

    public function test_health_and_monitor_actions_persist_results_alerts_and_audit_events(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Operations center test failure.',
            'failed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.operations-center.health'), ['note' => 'Manual readiness check'])
            ->assertRedirect();

        $this->assertDatabaseCount(HealthCheckRun::class, 4);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'operations.health_checks_run']);

        $this->actingAs($this->admin)
            ->post(route('admin.operations-center.monitor'), ['note' => 'Investigate failed queue work'])
            ->assertRedirect();

        $this->assertDatabaseHas(OperationalAlert::class, [
            'key' => 'queue.failed_jobs',
            'source' => 'monitor',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'operations.monitor_run']);

        DB::table('failed_jobs')->delete();
        $this->actingAs($this->admin)
            ->post(route('admin.operations-center.monitor'), ['note' => 'Confirm queue recovery'])
            ->assertRedirect();

        $this->assertDatabaseHas(OperationalAlert::class, [
            'key' => 'queue.failed_jobs',
            'status' => 'resolved',
        ]);
    }

    public function test_backup_verification_failed_job_and_alert_bulk_actions_are_controlled_and_audited(): void
    {
        Bus::fake();
        $backup = DatabaseBackup::query()->create([
            'driver' => 'mysql',
            'connection_name' => 'mysql',
            'database_name' => 'scratch-test',
            'status' => DatabaseBackupStatus::Succeeded,
            'verification_status' => BackupVerificationStatus::NotRequested,
            'started_at' => now(),
            'completed_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.backups.bulk'), [
                'ids' => [$backup->id],
                'action' => 'verify',
                'note' => 'Quarterly restore verification',
            ])
            ->assertRedirect();

        Bus::assertDispatched(fn (VerifyDatabaseBackupJob $job): bool => $job->backupId === $backup->id);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'operations.backup_verification_queued']);

        $failedId = DB::table('failed_jobs')->insertGetId([
            'uuid' => Str::uuid()->toString(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Forget this failed job.',
            'failed_at' => now(),
        ]);
        $alert = OperationalAlert::query()->create([
            'key' => 'test.alert',
            'source' => 'test',
            'severity' => 'warning',
            'status' => 'open',
            'title' => 'Focused alert',
            'message' => 'Needs acknowledgement.',
            'occurrence_count' => 1,
            'first_detected_at' => now(),
            'last_detected_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.failed-jobs.bulk'), [
                'ids' => [$failedId],
                'action' => 'forget',
                'note' => 'Obsolete test payload',
            ])
            ->assertRedirect();
        $this->assertDatabaseMissing('failed_jobs', ['id' => $failedId]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'operations.failed_job_forget']);

        $this->actingAs($this->admin)
            ->post(route('admin.operations.alerts.bulk'), [
                'ids' => [$alert->id],
                'action' => 'acknowledge',
                'note' => 'Assigned to platform operations',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(OperationalAlert::class, [
            'id' => $alert->id,
            'status' => 'acknowledged',
            'acknowledged_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'operations.alert_acknowledge']);
    }

    public function test_database_logging_redacts_secrets_and_operations_exports_are_audited(): void
    {
        Log::forgetChannel('database');
        Log::channel('database')->error('Paddle request failed with Bearer secret-value', [
            'api_key' => 'pdl_live_never_store_this',
            'nested' => ['webhook_secret' => 'do-not-store'],
        ]);

        $entry = ApplicationLogEntry::query()->latest()->firstOrFail();
        $this->assertSame('[REDACTED]', $entry->context['api_key']);
        $this->assertSame('[REDACTED]', $entry->context['nested']['webhook_secret']);
        $this->assertStringNotContainsString('secret-value', $entry->message);

        $response = $this->actingAs($this->admin)->get(route('admin.operational-records.export', [
            'resource' => 'application_logs',
            'status' => 'error',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Paddle request failed', $response->streamedContent());
        $this->assertDatabaseHas(AuditLog::class, ['action' => 'admin.operational_records.exported']);
    }

    public function test_operations_authorization_separates_view_and_sensitive_actions(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleName::SubAdmin->value);
        $viewer->givePermissionTo('admin.application_logs.view');

        $this->actingAs($viewer)
            ->get(route('admin.operations.logs.index'))
            ->assertOk();

        $this->actingAs($viewer)
            ->post(route('admin.operations-center.health'), ['note' => 'Unauthorized check'])
            ->assertForbidden();

        $viewer->givePermissionTo('admin.health_checks.view');
        $this->actingAs($viewer)
            ->get(route('admin.operations-center.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('capabilities.health', false)
                ->where('capabilities.backup', false));

        $this->assertTrue(Permission::query()->where('name', 'admin.operations.manage_alerts')->exists());
    }

    public function test_scheduler_monitor_records_heartbeat_history(): void
    {
        $this->artisan('platform:monitor', ['--no-alerts' => true])->assertSuccessful();

        $this->assertDatabaseHas(SchedulerHeartbeat::class, [
            'name' => 'platform.monitor',
            'status' => 'healthy',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.operations-center.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('snapshot.status', 'operational')
                ->where('snapshot.cards.1.tone', 'healthy')
                ->where('snapshot.cards.3.tone', 'healthy'));
    }

    public function test_audit_and_approval_histories_are_searchable_read_only_resources(): void
    {
        $course = Course::factory()->create();
        AuditLog::query()->create([
            'actor_id' => $this->admin->id,
            'action' => 'course.reviewed',
            'auditable_type' => $course->getMorphClass(),
            'auditable_id' => $course->id,
        ]);
        ApprovalHistory::query()->create([
            'subject_type' => $course->getMorphClass(),
            'subject_id' => $course->id,
            'actor_id' => $this->admin->id,
            'decision' => 'approved',
            'from_status' => 'submitted',
            'to_status' => 'published',
            'note' => 'Ownership and content verified.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.operations.audit-logs.index', ['search' => 'course.reviewed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.action', 'course.reviewed')
                ->where('canEdit', false));

        $this->actingAs($this->admin)
            ->get(route('admin.operations.approval-histories.index', ['category' => 'approved']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('rows.data', 1)
                ->where('rows.data.0.transition', 'submitted -> published')
                ->where('canDelete', false));
    }
}
