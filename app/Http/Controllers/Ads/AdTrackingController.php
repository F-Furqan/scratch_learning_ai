<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdCreative;
use App\Models\AdZone;
use App\Services\Ads\AdTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdTrackingController extends Controller
{
    public function __construct(
        private readonly AdTracker $tracker,
    ) {}

    public function impression(Request $request): JsonResponse
    {
        $data = $request->validate([
            'creative_id' => ['required', 'integer', 'exists:ad_creatives,id'],
            'zone_id' => ['nullable', 'integer', 'exists:ad_zones,id'],
            'url' => ['nullable', 'string', 'max:2048'],
            'metadata' => ['array'],
        ]);

        $creative = AdCreative::query()
            ->with(['campaign', 'zone'])
            ->whereKey($data['creative_id'])
            ->firstOrFail();

        abort_unless($creative->isActive(), 404);

        $impression = $this->tracker->impression(
            $creative,
            $request,
            isset($data['zone_id']) ? AdZone::query()->find((int) $data['zone_id']) : null,
            is_array($data['metadata'] ?? null) ? $data['metadata'] : [],
        );

        return response()->json([
            'data' => [
                'id' => $impression->id,
                'tracked' => true,
            ],
        ]);
    }

    public function click(Request $request, AdCreative $creative): RedirectResponse
    {
        $creative->load(['campaign', 'zone']);

        abort_unless($creative->isActive(), 404);

        $this->tracker->click($creative, $request, $creative->zone);

        return redirect()->away($creative->destinationUrl() ?: url('/'));
    }
}
