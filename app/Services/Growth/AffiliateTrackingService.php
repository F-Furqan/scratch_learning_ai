<?php

namespace App\Services\Growth;

use App\Enums\AffiliateStatus;
use App\Enums\ReferralConversionStatus;
use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use App\Models\PaymentCheckout;
use App\Models\ReferralConversion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AffiliateTrackingService
{
    public const AFFILIATE_COOKIE = 'affiliate_code';

    public const VISITOR_COOKIE = 'growth_visitor_id';

    public function activePartner(string $code): ?AffiliatePartner
    {
        return AffiliatePartner::query()
            ->where('code', strtoupper(trim($code)))
            ->where('status', AffiliateStatus::Active->value)
            ->first();
    }

    public function visitorId(Request $request): string
    {
        $visitorId = $request->string('visitor_id')->toString();
        $cookieVisitorId = $request->cookie(self::VISITOR_COOKIE);

        if (blank($visitorId) && is_string($cookieVisitorId)) {
            $visitorId = $cookieVisitorId;
        }

        return filled($visitorId) ? Str::limit($visitorId, 64, '') : (string) Str::uuid();
    }

    public function recordVisit(AffiliatePartner $partner, Request $request, ?User $user = null): AffiliateVisit
    {
        $visitorId = $this->visitorId($request);
        $cookieDays = max(1, (int) $partner->cookie_days);

        return AffiliateVisit::query()->create([
            'affiliate_partner_id' => $partner->id,
            'user_id' => $user?->id,
            'visitor_id' => $visitorId,
            'landing_url' => $request->fullUrl(),
            'referrer_url' => $request->headers->get('referer'),
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent_hash' => $request->userAgent() ? hash('sha256', (string) $request->userAgent()) : null,
            'clicked_at' => now(),
            'expires_at' => now()->addDays($cookieDays),
        ]);
    }

    public function conversionForCheckout(PaymentCheckout $checkout, ?string $affiliateCode, ?string $visitorId, ?User $user = null): ?ReferralConversion
    {
        $affiliateCode = strtoupper(trim((string) $affiliateCode));

        if ($affiliateCode === '') {
            return null;
        }

        $partner = $this->activePartner($affiliateCode);

        if (! $partner) {
            return null;
        }

        $visit = $visitorId
            ? AffiliateVisit::query()
                ->where('affiliate_partner_id', $partner->id)
                ->where('visitor_id', $visitorId)
                ->where('expires_at', '>=', now())
                ->latest('clicked_at')
                ->first()
            : null;

        return ReferralConversion::query()->updateOrCreate(
            ['payment_checkout_id' => $checkout->id, 'affiliate_partner_id' => $partner->id],
            [
                'affiliate_visit_id' => $visit?->id,
                'user_id' => $user?->id,
                'status' => ReferralConversionStatus::Pending,
                'metadata' => [
                    'affiliate_code' => $partner->code,
                    'visitor_id' => $visitorId,
                ],
            ],
        );
    }
}
