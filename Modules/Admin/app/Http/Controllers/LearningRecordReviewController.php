<?php

namespace Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Admin\Http\Requests\Learning\ShowLearningRecordRequest;
use Modules\Admin\Http\Requests\Learning\UpdateLearningRecordRequest;
use Modules\Admin\Services\LearningRecordReviewService;

final class LearningRecordReviewController extends Controller
{
    public function __construct(private readonly LearningRecordReviewService $reviews) {}

    public function show(ShowLearningRecordRequest $request, string $type, int $id): Response
    {
        $record = $this->reviews->find($type, $id);

        return Inertia::render('admin/learning/RecordReview', $this->reviews->payload($type, $record));
    }

    public function update(UpdateLearningRecordRequest $request, string $type, int $id): RedirectResponse
    {
        $record = $this->reviews->find($type, $id);
        $this->reviews->update($request, $type, $record);

        return back()->with('success', 'Learning record updated with an audited correction.');
    }
}
