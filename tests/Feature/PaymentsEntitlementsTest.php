<?php

namespace Tests\Feature;

use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentEntitlementStatus;
use App\Enums\PaymentEntitlementType;
use App\Enums\PaymentProductType;
use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CoursePurchase;
use App\Models\PaymentAuditLog;
use App\Models\PaymentCheckout;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentSubscription;
use App\Models\PaymentWebhookEvent;
use App\Models\TeamAccount;
use App\Models\TeamSeat;
use App\Models\User;
use App\Services\Payments\CourseAccessService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PaymentsEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.paddle.webhook_secret', 'phase8_secret');
        Config::set('payments.paddle.webhook_tolerance_seconds', 300);
        Config::set('payments.paddle.api_key', 'pdl_sdbx_apikey_test');
        Config::set('payments.paddle.environment', 'sandbox');
        Config::set('payments.paddle.checkout_success_url', 'https://scratch.test/checkout/success');
    }

    public function test_course_checkout_creates_paddle_transaction_and_local_session(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create(['is_free' => false]);
        $product = PaymentProduct::factory()->course($course)->create();
        $price = PaymentPrice::factory()->create([
            'payment_product_id' => $product->id,
            'amount' => 14900,
            'paddle_price_id' => 'pri_01coursecheckout000000000000',
        ]);

        Http::fake([
            'https://sandbox-api.paddle.com/transactions*' => Http::response([
                'data' => [
                    'id' => 'txn_01coursecheckout000000000000',
                    'checkout' => [
                        'url' => 'https://pay.scratch.test/checkout?_ptxn=txn_01coursecheckout000000000000',
                    ],
                ],
            ], 201),
        ]);

        $this->actingAs($user)
            ->postJson(route('checkout.courses.store', $course->slug))
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://pay.scratch.test/checkout?_ptxn=txn_01coursecheckout000000000000');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions?include=checkout'
            && $request->hasHeader('Authorization', 'Bearer pdl_sdbx_apikey_test')
            && data_get($request->data(), 'collection_mode') === 'automatic'
            && data_get($request->data(), 'items.0.price_id') === $price->paddle_price_id
            && data_get($request->data(), 'custom_data.user_id') === $user->id
            && data_get($request->data(), 'custom_data.course_id') === $course->id
            && data_get($request->data(), 'checkout.url') === 'https://scratch.test/checkout/success');

        $this->assertDatabaseHas(PaymentCheckout::class, [
            'user_id' => $user->id,
            'payment_price_id' => $price->id,
            'course_id' => $course->id,
            'status' => 'ready',
            'paddle_transaction_id' => 'txn_01coursecheckout000000000000',
        ]);

        $this->assertDatabaseHas(PaymentAuditLog::class, [
            'action' => 'payment.checkout.created',
            'user_id' => $user->id,
        ]);
    }

    public function test_completed_course_webhook_grants_purchase_entitlement_and_unlocks_lessons(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create(['is_free' => false]);
        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'content' => 'Paid lesson body.',
            'is_free' => false,
            'is_paid' => true,
        ]);
        $product = PaymentProduct::factory()->course($course)->create();
        $price = PaymentPrice::factory()->create(['payment_product_id' => $product->id]);
        $checkout = PaymentCheckout::factory()->create([
            'user_id' => $user->id,
            'payment_price_id' => $price->id,
            'course_id' => $course->id,
            'paddle_transaction_id' => 'txn_01coursepaid0000000000000',
            'custom_data' => [
                'checkout_id' => 1,
                'user_id' => $user->id,
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'product_type' => PaymentProductType::Course->value,
                'course_id' => $course->id,
                'quantity' => 1,
            ],
        ]);

        $payload = $this->transactionPayload(
            'evt_01coursepaid',
            'txn_01coursepaid0000000000000',
            $checkout->custom_data,
            $price,
        );

        $this->postPaddleWebhook($payload)->assertOk();
        $this->postPaddleWebhook($payload)->assertOk()->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount(PaymentOrder::class, 1);
        $this->assertDatabaseCount(CoursePurchase::class, 1);
        $this->assertDatabaseCount(PaymentEntitlement::class, 1);
        $this->assertDatabaseHas(CoursePurchase::class, [
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas(PaymentEntitlement::class, [
            'user_id' => $user->id,
            'type' => PaymentEntitlementType::Course->value,
            'status' => PaymentEntitlementStatus::Active->value,
        ]);
        $this->assertDatabaseHas(PaymentWebhookEvent::class, [
            'event_id' => 'evt_01coursepaid',
            'status' => 'processed',
            'attempts' => 1,
        ]);
        $this->assertDatabaseHas(PaymentReconciliationRecord::class, [
            'event_id' => 'evt_01coursepaid',
            'record_type' => 'transaction',
            'status' => 'reconciled',
        ]);

        $this->actingAs($user)
            ->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lesson.is_locked', false)
                ->where('lesson.content', 'Paid lesson body.'),
            );
    }

    public function test_team_subscription_webhook_grants_team_seats_and_premium_access(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);
        $team = TeamAccount::factory()->create([
            'owner_id' => $user->id,
            'seat_limit' => 3,
        ]);
        $product = PaymentProduct::factory()->team()->create();
        $price = PaymentPrice::factory()->monthly()->create(['payment_product_id' => $product->id]);

        $payload = $this->subscriptionPayload(
            'evt_01teamactive',
            'sub_01teamactive000000000000',
            'active',
            [
                'user_id' => $user->id,
                'team_account_id' => $team->id,
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'product_type' => PaymentProductType::Team->value,
                'quantity' => 5,
            ],
            $price,
            5,
        );

        $this->postPaddleWebhook($payload)->assertOk();

        $team->refresh();

        $this->assertSame(5, $team->seat_limit);
        $this->assertSame('active', $team->status->value);
        $this->assertDatabaseHas(TeamSeat::class, [
            'team_account_id' => $team->id,
            'user_id' => $user->id,
            'email' => 'owner@example.com',
            'role' => 'owner',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas(PaymentEntitlement::class, [
            'team_account_id' => $team->id,
            'type' => PaymentEntitlementType::PremiumLibrary->value,
            'status' => PaymentEntitlementStatus::Active->value,
        ]);

        $paidCourse = Course::factory()->published()->create(['is_free' => false]);

        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($user, $paidCourse));

        $pausedPayload = $this->subscriptionPayload(
            'evt_01teampaused',
            'sub_01teamactive000000000000',
            'paused',
            [
                'user_id' => $user->id,
                'team_account_id' => $team->id,
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'product_type' => PaymentProductType::Team->value,
                'quantity' => 5,
            ],
            $price,
            5,
        );

        $this->postPaddleWebhook($pausedPayload)->assertOk();

        $this->assertDatabaseHas(PaymentEntitlement::class, [
            'team_account_id' => $team->id,
            'type' => PaymentEntitlementType::PremiumLibrary->value,
            'status' => PaymentEntitlementStatus::Revoked->value,
        ]);
    }

    public function test_ad_free_subscription_grants_ad_free_access(): void
    {
        $user = User::factory()->create();
        $product = PaymentProduct::factory()->adFree()->create();
        $price = PaymentPrice::factory()->monthly()->create(['payment_product_id' => $product->id]);

        $payload = $this->subscriptionPayload(
            'evt_01adfreeactive',
            'sub_01adfree000000000000000',
            'active',
            [
                'user_id' => $user->id,
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'product_type' => PaymentProductType::AdFree->value,
                'quantity' => 1,
            ],
            $price,
        );

        $this->postPaddleWebhook($payload)->assertOk();

        $this->assertTrue(app(CourseAccessService::class)->hasAdFreePlan($user));
        $this->assertDatabaseHas(PaymentSubscription::class, [
            'user_id' => $user->id,
            'paddle_subscription_id' => 'sub_01adfree000000000000000',
            'status' => 'active',
        ]);
    }

    public function test_admin_can_manage_payment_products_and_prices(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin)
            ->get(route('admin.payments.products.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('resource', 'payment_products')
                ->where('title', 'Payment Products'),
            );

        $this->actingAs($admin)
            ->post(route('admin.payments.products.store'), [
                'name' => 'Advanced Premium Library',
                'description' => 'All access.',
                'type' => PaymentProductType::PremiumLibrary->value,
                'status' => 'active',
                'tax_category' => 'training-services',
                'metadata_content' => '{"tier":"premium"}',
            ])
            ->assertRedirect();

        $product = PaymentProduct::query()->where('name', 'Advanced Premium Library')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.payments.prices.store'), [
                'payment_product_id' => $product->id,
                'name' => 'Premium Monthly',
                'billing_interval' => PaymentBillingInterval::Month->value,
                'is_recurring' => true,
                'currency' => 'usd',
                'amount' => 2900,
                'is_active' => true,
                'metadata_content' => '{"source":"admin"}',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(PaymentPrice::class, [
            'payment_product_id' => $product->id,
            'name' => 'Premium Monthly',
            'currency' => 'USD',
            'amount' => 2900,
        ]);
        $this->assertDatabaseHas(AuditLog::class, [
            'actor_id' => $admin->id,
            'action' => 'admin.payment_products.created',
            'auditable_id' => $product->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    private function transactionPayload(string $eventId, string $transactionId, array $customData, PaymentPrice $price): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => 'transaction.completed',
            'occurred_at' => now()->toISOString(),
            'data' => [
                'id' => $transactionId,
                'status' => 'completed',
                'customer_id' => 'ctm_01phase800000000000000000',
                'currency_code' => 'USD',
                'custom_data' => $customData,
                'items' => [
                    [
                        'quantity' => 1,
                        'price' => [
                            'id' => $price->paddle_price_id,
                        ],
                    ],
                ],
                'details' => [
                    'totals' => [
                        'subtotal' => (string) $price->amount,
                        'tax' => '0',
                        'discount' => '0',
                        'total' => (string) $price->amount,
                    ],
                ],
                'billed_at' => now()->toISOString(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $customData
     * @return array<string, mixed>
     */
    private function subscriptionPayload(string $eventId, string $subscriptionId, string $status, array $customData, PaymentPrice $price, int $quantity = 1): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => match ($status) {
                'active' => 'subscription.activated',
                'paused' => 'subscription.paused',
                default => 'subscription.updated',
            },
            'occurred_at' => now()->toISOString(),
            'data' => [
                'id' => $subscriptionId,
                'status' => $status,
                'customer_id' => 'ctm_01phase8subscription000000',
                'currency_code' => 'USD',
                'custom_data' => $customData,
                'items' => [
                    [
                        'quantity' => $quantity,
                        'price' => [
                            'id' => $price->paddle_price_id,
                        ],
                    ],
                ],
                'current_billing_period' => [
                    'starts_at' => now()->toISOString(),
                    'ends_at' => now()->addMonth()->toISOString(),
                ],
                'next_billed_at' => now()->addMonth()->toISOString(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postPaddleWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('api.webhooks.paddle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => $this->paddleSignature($body, 'phase8_secret'),
            'REMOTE_ADDR' => '198.51.100.88',
        ], $body);
    }

    private function paddleSignature(string $payload, string $secret): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        return "ts={$timestamp};h1={$signature}";
    }
}
