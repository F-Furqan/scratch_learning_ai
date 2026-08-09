<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\AffiliateTrackingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateRedirectController extends Controller
{
    public function __construct(
        private readonly AffiliateTrackingService $affiliates,
    ) {}

    public function __invoke(Request $request, string $code): RedirectResponse
    {
        $partner = $this->affiliates->activePartner($code);

        abort_unless($partner !== null, 404);

        $visit = $this->affiliates->recordVisit($partner, $request, $request->user());
        $target = $this->targetUrl($request);
        $minutes = max(1, (int) $partner->cookie_days) * 24 * 60;

        return redirect($target)
            ->withCookie(cookie(AffiliateTrackingService::AFFILIATE_COOKIE, $partner->code, $minutes))
            ->withCookie(cookie(AffiliateTrackingService::VISITOR_COOKIE, $visit->visitor_id, $minutes));
    }

    private function targetUrl(Request $request): string
    {
        $target = $request->string('to')->toString() ?: route('home');

        if (Str::startsWith($target, ['http://', 'https://', '//'])) {
            return route('home');
        }

        return Str::startsWith($target, '/') ? $target : route('home');
    }
}
