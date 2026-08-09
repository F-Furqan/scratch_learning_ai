<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\AbExperiment;
use App\Services\Growth\AbTestingService;
use App\Services\Growth\AffiliateTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbExperimentController extends Controller
{
    public function __construct(
        private readonly AbTestingService $experiments,
    ) {}

    public function show(Request $request, AbExperiment $experiment): JsonResponse
    {
        $assignment = $this->experiments->assign($experiment->key, $request, $request->user());

        abort_unless($assignment !== null, 404);

        $assignment->loadMissing(['experiment', 'variant']);

        return response()->json([
            'data' => [
                'assignment_id' => $assignment->id,
                'experiment_key' => $assignment->experiment?->key,
                'surface' => $assignment->experiment?->surface,
                'variant_key' => $assignment->variant?->key,
                'payload' => $assignment->variant?->payload ?: [],
            ],
        ])->withCookie(cookie(AffiliateTrackingService::VISITOR_COOKIE, (string) $assignment->visitor_id, 60 * 24 * 365));
    }
}
