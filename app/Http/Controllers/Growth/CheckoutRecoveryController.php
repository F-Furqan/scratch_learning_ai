<?php

namespace App\Http\Controllers\Growth;

use App\Http\Controllers\Controller;
use App\Models\AbandonedCheckoutRecovery;
use App\Services\Growth\CheckoutRecoveryService;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CheckoutRecoveryController extends Controller
{
    public function __construct(
        private readonly CheckoutRecoveryService $recoveries,
    ) {}

    public function __invoke(Request $request, AbandonedCheckoutRecovery $recovery): RedirectResponse
    {
        $expiresAt = $recovery->getAttribute('expires_at');

        abort_if($expiresAt instanceof CarbonInterface && $expiresAt->isPast(), 410);

        $this->recoveries->markReminderOpened($recovery);
        $checkoutUrl = $recovery->checkout?->checkout_url;

        return filled($checkoutUrl)
            ? redirect()->away((string) $checkoutUrl)
            : redirect()->route('home');
    }
}
