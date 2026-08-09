<?php

namespace Tests\Feature;

use App\Enums\DiscountType;
use App\Enums\GiftPurchaseStatus;
use App\Enums\GrowthStatus;
use App\Enums\PaymentEntitlementType;
use App\Enums\PaymentProductType;
use App\Enums\RoleName;
use App\Models\AbandonedCheckoutRecovery;
use App\Models\AbAssignment;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseEnrollment;
use App\Models\CoursePurchase;
use App\Models\GiftPurchase;
use App\Models\LeadMagnet;
use App\Models\LeadSubmission;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentDiscountRedemption;
use App\Models\PaymentEntitlement;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use App\Models\ReferralConversion;
use App\Models\SocialShareImage;
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

class GrowthMonetizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('payments.paddle.webhook_secret', 'phase12_secret');
        Config::set('payments.paddle.webhook_tolerance_seconds', 300);
        Config::set('payments.paddle.api_key', 'pdl_sdbx_phase12_test');
        Config::set('payments.paddle.environment', 'sandbox');
        Config::set('payments.paddle.checkout_success_url', 'https://scratch.test/checkout/success');
    }

    public function test_checkout_carries_discount_gift_referral_recovery_and_ab_context_to_paddle(): void
    {
        $user = User::factory()->create(['email' => 'buyer@example.com']);
        $course = Course::factory()->published()->create(['is_free' => false]);
        $product = PaymentProduct::factory()->course($course)->create();
        $price = PaymentPrice::factory()->create([
            'payment_product_id' => $product->id,
            'amount' => 10000,
            'paddle_price_id' => 'pri_01phase12course0000000000',
        ]);
        $discount = PaymentDiscount::factory()->forPrice($price)->create([
            'code' => 'SAVE30',
            'type' => DiscountType::Percent,
            'value' => 30,
            'paddle_discount_id' => 'dsc_01phase12discount0000000',
        ]);
        $partner = AffiliatePartner::factory()->create(['code' => 'PARTNER12']);
        $experiment = AbExperiment::factory()->create(['key' => 'pricing_cta']);
        $variant = AbVariant::factory()->create([
            'ab_experiment_id' => $experiment->id,
            'key' => 'control',
            'weight' => 100,
            'payload' => ['cta' => 'Start learning'],
        ]);

        Http::fake([
            'https://sandbox-api.paddle.com/transactions*' => Http::response([
                'data' => [
                    'id' => 'txn_01phase12growth0000000000',
                    'checkout' => [
                        'url' => 'https://pay.scratch.test/checkout?_ptxn=txn_01phase12growth0000000000',
                    ],
                ],
            ], 201),
        ]);

        $this->actingAs($user)
            ->postJson(route('checkout.courses.store', $course->slug), [
                'discount_code' => 'SAVE30',
                'affiliate_code' => 'PARTNER12',
                'visitor_id' => 'visitor-phase-12',
                'ab_experiment_key' => 'pricing_cta',
                'gift_recipient_email' => 'friend@example.com',
                'gift_recipient_name' => 'Friend Learner',
                'gift_message' => 'Enjoy this one.',
                'recovery_email' => 'buyer@example.com',
            ])
            ->assertCreated()
            ->assertJsonPath('data.checkout_url', 'https://pay.scratch.test/checkout?_ptxn=txn_01phase12growth0000000000');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === 'https://sandbox-api.paddle.com/transactions?include=checkout'
            && data_get($request->data(), 'discount_id') === 'dsc_01phase12discount0000000'
            && data_get($request->data(), 'custom_data.growth.discount.code') === 'SAVE30'
            && data_get($request->data(), 'custom_data.growth.discount.amount') === 3000
            && data_get($request->data(), 'custom_data.growth.gift.recipient_email') === 'friend@example.com'
            && data_get($request->data(), 'custom_data.growth.affiliate.partner_id') === $partner->id
            && data_get($request->data(), 'custom_data.growth.ab_test.variant_id') === $variant->id);

        $checkout = PaymentCheckout::query()->firstOrFail();

        $this->assertSame($discount->id, $checkout->payment_discount_id);
        $this->assertSame(3000, $checkout->discount_amount);
        $this->assertDatabaseHas(PaymentDiscountRedemption::class, [
            'payment_discount_id' => $discount->id,
            'payment_checkout_id' => $checkout->id,
            'amount' => 3000,
        ]);
        $this->assertDatabaseHas(GiftPurchase::class, [
            'payment_checkout_id' => $checkout->id,
            'recipient_email' => 'friend@example.com',
            'status' => GiftPurchaseStatus::Pending->value,
        ]);
        $this->assertDatabaseHas(AbandonedCheckoutRecovery::class, [
            'payment_checkout_id' => $checkout->id,
            'email' => 'buyer@example.com',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas(ReferralConversion::class, [
            'affiliate_partner_id' => $partner->id,
            'payment_checkout_id' => $checkout->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas(AbAssignment::class, [
            'ab_experiment_id' => $experiment->id,
            'ab_variant_id' => $variant->id,
            'visitor_id' => 'visitor-phase-12',
        ]);
    }

    public function test_public_growth_endpoints_track_affiliate_lead_and_ab_activity(): void
    {
        $partner = AffiliatePartner::factory()->create(['code' => 'GROWTH12', 'cookie_days' => 20]);

        $this->get(route('growth.affiliate.redirect', ['code' => 'GROWTH12', 'to' => '/courses']))
            ->assertRedirect('/courses')
            ->assertCookie('affiliate_code', 'GROWTH12');

        $this->assertDatabaseHas(AffiliateVisit::class, [
            'affiliate_partner_id' => $partner->id,
        ]);

        $leadMagnet = LeadMagnet::factory()->create([
            'slug' => 'factory-launch-checklist',
            'delivery_url' => '/downloads/checklist.pdf',
        ]);

        $this->postJson(route('growth.lead-magnets.submissions.store', $leadMagnet->slug), [
            'email' => 'lead@example.com',
            'name' => 'Lead Person',
        ])
            ->assertCreated()
            ->assertJsonPath('data.delivery_url', '/downloads/checklist.pdf');

        $this->assertDatabaseHas(LeadSubmission::class, [
            'lead_magnet_id' => $leadMagnet->id,
            'email' => 'lead@example.com',
            'status' => 'subscribed',
        ]);

        $experiment = AbExperiment::factory()->create(['key' => 'pricing_cta']);
        AbVariant::factory()->create([
            'ab_experiment_id' => $experiment->id,
            'key' => 'annual',
            'weight' => 100,
            'payload' => ['cta' => 'Choose annual'],
        ]);

        $this->getJson(route('growth.experiments.show', $experiment->key).'?visitor_id=visitor-public-growth')
            ->assertOk()
            ->assertJsonPath('data.variant_key', 'annual')
            ->assertJsonPath('data.payload.cta', 'Choose annual');

        $this->assertDatabaseHas(AbAssignment::class, [
            'ab_experiment_id' => $experiment->id,
            'visitor_id' => 'visitor-public-growth',
        ]);
    }

    public function test_completed_bundle_webhook_unlocks_all_bundle_courses(): void
    {
        $user = User::factory()->create();
        $firstCourse = Course::factory()->published()->create(['is_free' => false]);
        $secondCourse = Course::factory()->published()->create(['is_free' => false]);
        $bundle = CourseBundle::factory()->create();
        $bundle->courses()->sync([
            $firstCourse->id => ['sort_order' => 1],
            $secondCourse->id => ['sort_order' => 2],
        ]);
        $product = PaymentProduct::factory()->bundle($bundle)->create();
        $price = PaymentPrice::factory()->create([
            'payment_product_id' => $product->id,
            'amount' => 29900,
            'paddle_price_id' => 'pri_01phase12bundle000000000',
        ]);
        $checkout = PaymentCheckout::factory()->create([
            'user_id' => $user->id,
            'payment_price_id' => $price->id,
            'paddle_transaction_id' => 'txn_01phase12bundle00000000',
            'custom_data' => [
                'checkout_id' => 1,
                'user_id' => $user->id,
                'payment_price_id' => $price->id,
                'payment_product_id' => $product->id,
                'product_type' => PaymentProductType::Bundle->value,
                'quantity' => 1,
            ],
        ]);

        $payload = $this->transactionPayload(
            'evt_01phase12bundle',
            'txn_01phase12bundle00000000',
            $checkout->custom_data,
            $price,
        );

        $this->postPaddleWebhook($payload)->assertOk();

        $this->assertDatabaseHas(PaymentEntitlement::class, [
            'user_id' => $user->id,
            'type' => PaymentEntitlementType::Bundle->value,
            'entitlementable_type' => $bundle->getMorphClass(),
            'entitlementable_id' => $bundle->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseCount(CoursePurchase::class, 2);
        $this->assertDatabaseCount(CourseEnrollment::class, 2);
        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($user, $firstCourse));
        $this->assertTrue(app(CourseAccessService::class)->canAccessCourse($user, $secondCourse));
        $this->assertDatabaseHas(PaymentWebhookEvent::class, [
            'event_id' => 'evt_01phase12bundle',
            'status' => 'processed',
        ]);
        $this->assertDatabaseHas(PaymentReconciliationRecord::class, [
            'event_id' => 'evt_01phase12bundle',
            'status' => 'reconciled',
        ]);
    }

    public function test_public_course_and_blog_render_social_share_images(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Industrial Course']);
        $post = BlogPost::factory()->published()->create(['title' => 'Industrial Blog']);

        SocialShareImage::factory()->create([
            'shareable_type' => $course->getMorphClass(),
            'shareable_id' => $course->id,
            'image_url' => 'https://cdn.example.com/course-share.png',
            'title' => 'Course Share',
            'status' => GrowthStatus::Active,
        ]);
        SocialShareImage::factory()->create([
            'shareable_type' => $post->getMorphClass(),
            'shareable_id' => $post->id,
            'image_url' => 'https://cdn.example.com/blog-share.png',
            'title' => 'Blog Share',
            'status' => GrowthStatus::Active,
        ]);

        $this->get(route('public.courses.show', $course->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('course.social_share_image.url', 'https://cdn.example.com/course-share.png')
                ->where('seo.image', 'https://cdn.example.com/course-share.png'));

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('post.social_share_image.url', 'https://cdn.example.com/blog-share.png')
                ->where('seo.image', 'https://cdn.example.com/blog-share.png'));
    }

    public function test_admin_can_manage_growth_resources(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);
        $course = Course::factory()->published()->create();
        $product = PaymentProduct::factory()->course($course)->create();
        $price = PaymentPrice::factory()->create(['payment_product_id' => $product->id]);

        $this->actingAs($admin)
            ->get(route('admin.growth.discounts.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('resource', 'payment_discounts')
                ->where('title', 'Discounts & Coupons'));

        $this->actingAs($admin)
            ->post(route('admin.growth.discounts.store'), [
                'payment_product_id' => $product->id,
                'payment_price_id' => $price->id,
                'course_id' => $course->id,
                'name' => 'Launch Save',
                'code' => 'launch12',
                'type' => DiscountType::Percent->value,
                'value' => 20,
                'currency' => 'usd',
                'status' => GrowthStatus::Active->value,
                'is_launch_offer' => true,
                'per_user_limit' => 1,
                'metadata_content' => '{"source":"admin"}',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(PaymentDiscount::class, [
            'payment_product_id' => $product->id,
            'code' => 'LAUNCH12',
            'currency' => 'USD',
            'is_launch_offer' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.growth.lead-magnets.store'), [
                'title' => 'Factory Growth Guide',
                'slug' => 'factory-growth-guide',
                'description' => 'Guide for growth campaigns.',
                'status' => GrowthStatus::Active->value,
                'form_headline' => 'Get the guide',
                'delivery_url' => '/downloads/factory-growth-guide.pdf',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(LeadMagnet::class, [
            'slug' => 'factory-growth-guide',
            'status' => GrowthStatus::Active->value,
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
                'customer_id' => 'ctm_01phase12000000000000000',
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
     * @param  array<string, mixed>  $payload
     */
    private function postPaddleWebhook(array $payload): TestResponse
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', route('api.webhooks.paddle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => $this->paddleSignature($body, 'phase12_secret'),
            'REMOTE_ADDR' => '198.51.100.92',
        ], $body);
    }

    private function paddleSignature(string $payload, string $secret): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        return "ts={$timestamp};h1={$signature}";
    }
}
