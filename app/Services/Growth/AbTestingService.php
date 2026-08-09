<?php

namespace App\Services\Growth;

use App\Enums\GrowthStatus;
use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AbTestingService
{
    public function visitorId(Request $request): string
    {
        $visitorId = $request->string('visitor_id')->toString();
        $cookieVisitorId = $request->cookie(AffiliateTrackingService::VISITOR_COOKIE);

        if (blank($visitorId) && is_string($cookieVisitorId)) {
            $visitorId = $cookieVisitorId;
        }

        return filled($visitorId) ? Str::limit($visitorId, 64, '') : (string) Str::uuid();
    }

    public function assign(string $experimentKey, Request $request, ?User $user = null): ?AbAssignment
    {
        $experiment = AbExperiment::query()
            ->where('key', $experimentKey)
            ->where('status', GrowthStatus::Active->value)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->with('variants')
            ->first();

        if (! $experiment || $experiment->variants->isEmpty()) {
            return null;
        }

        $visitorId = $this->visitorId($request);
        $existing = AbAssignment::query()
            ->where('ab_experiment_id', $experiment->id)
            ->where('visitor_id', $visitorId)
            ->with('variant')
            ->first();

        if ($existing) {
            return $existing;
        }

        $variant = $this->chooseVariant($experiment->variants);

        $assignment = AbAssignment::query()->create([
            'ab_experiment_id' => $experiment->id,
            'ab_variant_id' => $variant->id,
            'user_id' => $user?->id,
            'visitor_id' => $visitorId,
            'assigned_at' => now(),
            'metadata' => [
                'surface' => $experiment->surface,
            ],
        ]);

        $variant->increment('views_count');

        return $assignment->load('variant', 'experiment');
    }

    /**
     * @param  Collection<int, AbVariant>  $variants
     */
    private function chooseVariant(Collection $variants): AbVariant
    {
        $totalWeight = max(1, $variants->sum(fn (AbVariant $variant): int => max(0, (int) $variant->weight)));
        $cursor = random_int(1, $totalWeight);
        $running = 0;

        foreach ($variants as $variant) {
            $running += max(0, (int) $variant->weight);

            if ($cursor <= $running) {
                return $variant;
            }
        }

        return $variants->first();
    }
}
