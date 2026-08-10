<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Http\Requests\Operations\ExportOperationalRecordsRequest;
use Modules\Admin\Query\AdminResourceQueryFactory;
use Modules\Admin\Query\AdminResourceQueryFilter;
use Modules\Admin\Services\CommerceOperationsAdminService;
use Modules\Admin\Services\CommunityOperationsAdminService;
use Modules\Admin\Services\OperationsCenterAdminService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class OperationalRecordExportController extends Controller
{
    public function __construct(
        private readonly AdminResourceQueryFactory $queries,
        private readonly AdminResourceQueryFilter $filters,
        private readonly CommerceOperationsAdminService $commerce,
        private readonly CommunityOperationsAdminService $community,
        private readonly OperationsCenterAdminService $operations,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(ExportOperationalRecordsRequest $request, string $resource): StreamedResponse
    {
        $service = match (true) {
            $this->commerce->supports($resource) => $this->commerce,
            $this->community->supports($resource) => $this->community,
            default => $this->operations,
        };
        $columns = $service->columns($resource);
        $query = $this->filters->apply($this->queries->make($resource), $resource, $request)->reorder('id');

        $this->audit->log($request, 'admin.operational_records.exported', metadata: [
            'resource' => $resource,
            'filters' => $request->safe()->only(['search', 'status', 'category']),
        ]);

        return response()->streamDownload(function () use ($query, $service, $resource, $columns): void {
            $handle = fopen('php://output', 'w');
            abort_unless(is_resource($handle), 500);
            fputcsv($handle, array_column($columns, 'label'));

            $query->chunkById(250, function ($records) use ($handle, $service, $resource, $columns): void {
                foreach ($records as $record) {
                    abort_unless($record instanceof Model, 500);
                    $row = $service->row($resource, $record);
                    fputcsv($handle, array_map(
                        fn (array $column): string|int|float => $this->csvValue($row[$column['key']] ?? ''),
                        $columns,
                    ));
                }
            });

            fclose($handle);
        }, $resource.'-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function csvValue(mixed $value): string|int|float
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_SLASHES);
        }

        $string = (string) $value;

        return preg_match('/^[=+\-@]/', $string) === 1 ? "'".$string : $string;
    }
}
