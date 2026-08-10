<?php

namespace Modules\Admin\Services;

use App\Enums\DatabaseBackupStatus;
use App\Jobs\VerifyDatabaseBackupJob;
use App\Models\DatabaseBackup;
use App\Models\FailedJob;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

final readonly class OperationsCenterActionService
{
    public function __construct(private AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function bulk(Request $request, string $resource, array $data): void
    {
        $note = trim((string) ($data['note'] ?? ''));

        if ($note === '') {
            throw ValidationException::withMessages(['note' => 'An operational reason is required.']);
        }

        match ($resource) {
            'database_backups' => $this->verifyBackups($request, $data, $note),
            'failed_jobs' => $this->handleFailedJobs($request, $data, $note),
            'operational_alerts' => $this->handleAlerts($request, $data, $note),
            default => abort(405, 'This operational record is immutable.'),
        };
    }

    /** @param array<string, mixed> $data */
    private function verifyBackups(Request $request, array $data, string $note): void
    {
        $this->authorize($request, 'admin.operations.backup');
        abort_unless($data['action'] === 'verify', 422, 'Unsupported backup action.');

        $backups = DatabaseBackup::query()->whereKey($data['ids'])->get();

        foreach ($backups as $backup) {
            if ($backup->status !== DatabaseBackupStatus::Succeeded) {
                throw ValidationException::withMessages([
                    'ids' => "Backup #{$backup->getKey()} is not successful and cannot be verified.",
                ]);
            }

            VerifyDatabaseBackupJob::dispatch((int) $backup->getKey());
            $this->audit->log($request, 'operations.backup_verification_queued', $backup, null, null, [
                'note' => $note,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleFailedJobs(Request $request, array $data, string $note): void
    {
        $this->authorize($request, 'admin.operations.manage_queue');
        abort_unless(in_array($data['action'], ['retry', 'forget'], true), 422, 'Unsupported queue action.');

        $jobs = FailedJob::query()->whereKey($data['ids'])->get();

        foreach ($jobs as $job) {
            $before = $job->toArray();

            if ($data['action'] === 'retry') {
                Queue::connection($job->connection)->pushRaw($job->payload, $job->queue);
            }

            DB::table('failed_jobs')->where('id', $job->getKey())->delete();
            $this->audit->log($request, 'operations.failed_job_'.$data['action'], $job, $before, null, [
                'uuid' => $job->uuid,
                'queue' => $job->queue,
                'note' => $note,
            ]);
        }
    }

    /** @param array<string, mixed> $data */
    private function handleAlerts(Request $request, array $data, string $note): void
    {
        $this->authorize($request, 'admin.operations.manage_alerts');
        abort_unless(in_array($data['action'], ['acknowledge', 'resolve'], true), 422, 'Unsupported alert action.');

        $alerts = OperationalAlert::query()->whereKey($data['ids'])->get();
        $userId = $request->user()?->getKey();

        foreach ($alerts as $alert) {
            $before = $alert->toArray();
            $values = $data['action'] === 'acknowledge'
                ? [
                    'status' => 'acknowledged',
                    'acknowledged_by' => $userId,
                    'acknowledged_at' => now(),
                ]
                : [
                    'status' => 'resolved',
                    'resolved_by' => $userId,
                    'resolved_at' => now(),
                    'resolution_note' => $note,
                ];

            $alert->forceFill($values)->save();
            $this->audit->log($request, 'operations.alert_'.$data['action'], $alert, $before, $alert->fresh()?->toArray(), [
                'note' => $note,
            ]);
        }
    }

    private function authorize(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can($permission), 403);
    }
}
