<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\PaymentCheckout;
use App\Models\PaymentPrice;
use App\Models\TeamAccount;
use App\Services\Payments\PaymentCheckoutService;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PaymentCheckoutController extends Controller
{
    public function __construct(
        private readonly PaymentCheckoutService $checkouts,
    ) {}

    public function store(Request $request, PaymentPrice $price): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'team_account_id' => ['nullable', 'integer', 'exists:team_accounts,id'],
            'discount_code' => ['nullable', 'string', 'max:64'],
            'coupon' => ['nullable', 'string', 'max:64'],
            'affiliate_code' => ['nullable', 'string', 'max:64'],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'ab_experiment_key' => ['nullable', 'string', 'max:128'],
            'gift_recipient_email' => ['nullable', 'email', 'max:255'],
            'gift_recipient_name' => ['nullable', 'string', 'max:255'],
            'gift_message' => ['nullable', 'string', 'max:1000'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $course = isset($data['course_id']) ? Course::query()->find((int) $data['course_id']) : null;
        $team = isset($data['team_account_id'])
            ? TeamAccount::query()->where('owner_id', $user->id)->find((int) $data['team_account_id'])
            : null;

        if (isset($data['team_account_id']) && ! $team) {
            throw ValidationException::withMessages([
                'team_account_id' => 'You can only create checkouts for teams you own.',
            ]);
        }

        $checkout = $this->checkouts->create(
            $user,
            $price,
            (int) ($data['quantity'] ?? 1),
            $course,
            $team,
            $request,
        );

        return $this->checkoutResponse($request, $checkout);
    }

    public function course(Request $request, Course $course): JsonResponse|RedirectResponse
    {
        $request->validate([
            'discount_code' => ['nullable', 'string', 'max:64'],
            'coupon' => ['nullable', 'string', 'max:64'],
            'affiliate_code' => ['nullable', 'string', 'max:64'],
            'visitor_id' => ['nullable', 'string', 'max:64'],
            'ab_experiment_key' => ['nullable', 'string', 'max:128'],
            'gift_recipient_email' => ['nullable', 'email', 'max:255'],
            'gift_recipient_name' => ['nullable', 'string', 'max:255'],
            'gift_message' => ['nullable', 'string', 'max:1000'],
            'recovery_email' => ['nullable', 'email', 'max:255'],
        ]);

        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $price = PaymentPrice::query()
            ->where('is_active', true)
            ->whereHas('product', fn ($query) => $query
                ->where('course_id', $course->id)
                ->where('type', 'course')
                ->where('status', 'active'))
            ->with('product')
            ->firstOrFail();

        $checkout = $this->checkouts->create($user, $price, 1, $course, null, $request);

        return $this->checkoutResponse($request, $checkout);
    }

    private function checkoutResponse(Request $request, PaymentCheckout $checkout): JsonResponse|RedirectResponse
    {
        $status = $checkout->getAttribute('status');

        $payload = [
            'data' => [
                'id' => $checkout->id,
                'status' => $status instanceof BackedEnum ? (string) $status->value : (string) $status,
                'paddle_transaction_id' => $checkout->paddle_transaction_id,
                'checkout_url' => $checkout->checkout_url,
            ],
        ];

        if ($request->expectsJson()) {
            return response()->json($payload, 201);
        }

        return filled($checkout->checkout_url)
            ? redirect()->away((string) $checkout->checkout_url)
            : back()->with('success', 'Checkout created.');
    }
}
