<?php

namespace App\Services\Ads;

use App\Models\AdClick;
use App\Models\AdCreative;
use App\Models\AdImpression;
use App\Models\AdZone;
use Illuminate\Http\Request;

class AdTracker
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function impression(AdCreative $creative, Request $request, ?AdZone $zone = null, array $metadata = []): AdImpression
    {
        return AdImpression::query()->create([
            ...$this->eventPayload($creative, $request, $zone, $metadata),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function click(AdCreative $creative, Request $request, ?AdZone $zone = null, array $metadata = []): AdClick
    {
        return AdClick::query()->create([
            ...$this->eventPayload($creative, $request, $zone, $metadata),
            'target_url' => $creative->destinationUrl(),
            'occurred_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function eventPayload(AdCreative $creative, Request $request, ?AdZone $zone, array $metadata): array
    {
        return [
            'ad_zone_id' => $zone?->id ?: $creative->ad_zone_id,
            'ad_campaign_id' => $creative->ad_campaign_id,
            'ad_creative_id' => $creative->id,
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'ip_hash' => $this->hashNullable($request->ip()),
            'user_agent_hash' => $this->hashNullable($request->userAgent()),
            'url' => $request->input('url') ?: $request->headers->get('referer'),
            'referrer' => $request->headers->get('referer'),
            'metadata' => $metadata,
        ];
    }

    private function hashNullable(?string $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        return hash('sha256', config('app.key').'|'.$value);
    }
}
