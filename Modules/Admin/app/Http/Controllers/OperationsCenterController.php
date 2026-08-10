<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\RunDatabaseBackupJob;
use App\Services\Admin\AuditLogger;
use App\Services\Operations\OperationalMonitor;
use App\Services\Operations\PlatformHealthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\Operations\RunOperationsActionRequest;
use Modules\Admin\Services\OperationsCenterOverviewService;

final class OperationsCenterController extends Controller
{
    public function __construct(
        private readonly OperationsCenterOverviewService $overview,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('admin/operations/Center', [
            'snapshot' => $this->overview->snapshot(),
            'capabilities' => [
                'backup' => $user?->can('admin.operations.backup') ?? false,
                'health' => $user?->can('admin.operations.run_health') ?? false,
                'monitor' => $user?->can('admin.operations.run_monitor') ?? false,
                'backupsEnabled' => (bool) config('operations.backups.enabled', false),
            ],
        ]);
    }

    public function backup(RunOperationsActionRequest $request): RedirectResponse
    {
        abort_unless((bool) config('operations.backups.enabled', false), 422, 'Backups are disabled until production backup settings are validated.');
        $data = $request->validated();

        RunDatabaseBackupJob::dispatch((bool) ($data['verify'] ?? false));
        $this->audit->log($request, 'operations.database_backup_queued', metadata: [
            'verify' => (bool) ($data['verify'] ?? false),
            'note' => $data['note'],
        ]);

        return back()->with('success', 'Database backup queued.');
    }

    public function health(RunOperationsActionRequest $request, PlatformHealthService $health): RedirectResponse
    {
        $report = $health->report();
        $this->audit->log($request, 'operations.health_checks_run', metadata: [
            'status' => $report['status'],
            'note' => $request->validated('note'),
        ]);

        return back()->with('success', 'Health checks completed with status: '.$report['status'].'.');
    }

    public function monitor(RunOperationsActionRequest $request, OperationalMonitor $monitor): RedirectResponse
    {
        $issues = $monitor->run();
        $this->audit->log($request, 'operations.monitor_run', metadata: [
            'issue_count' => count($issues),
            'note' => $request->validated('note'),
        ]);

        return back()->with('success', count($issues).' operational issue(s) detected.');
    }
}
