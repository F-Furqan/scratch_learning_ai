<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\Operations\ShowOperationalRecordRequest;
use Modules\Admin\Http\Requests\Operations\UpdateOperationalRecordRequest;
use Modules\Admin\Services\OperationalRecordActionService;

final class OperationalRecordActionController extends Controller
{
    public function __construct(private readonly OperationalRecordActionService $actions) {}

    public function show(ShowOperationalRecordRequest $request, string $type, int $id): Response
    {
        $record = $this->actions->find($type, $id);

        return Inertia::render('admin/learning/RecordReview', $this->actions->payload($type, $record));
    }

    public function update(UpdateOperationalRecordRequest $request, string $type, int $id): RedirectResponse
    {
        $record = $this->actions->find($type, $id);
        $this->actions->update($request, $type, $record);

        return back()->with('success', 'Operational action completed and audited.');
    }
}
