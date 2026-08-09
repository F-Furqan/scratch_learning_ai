<?php

namespace Tests\Feature;

use App\Enums\AnalyticsEventType;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\LessonProgressStatus;
use App\Enums\RoleName;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsFunnelSnapshot;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\CohortRetentionSnapshot;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseLesson;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\LessonProgress;
use App\Models\PaymentCheckout;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AnalyticsReportingProTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Config::set('payments.paddle.webhook_secret', 'phase13_secret');
        Config::set('payments.paddle.webhook_tolerance_seconds', 300);
        Config::set('payments.paddle.api_key', 'pdl_sdbx_phase13_test');
        Config::set('payments.paddle.environment', 'sandbox');
        Config::set('payments.paddle.checkout_success_url', 'https://scratch.test/checkout/success');
    }

    public function test_admin_reports_include_business_intelligence_sections(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        $author = User::factory()->create(['name' => 'Expert Author']);
        $student = User::factory()->create();
        $dropOffStudent = User::factory()->create();
        $course = Course::factory()->published()->create([
            'title' => 'Industrial Analytics Course',
            'is_free' => false,
        ]);
        $firstLesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Telemetry Basics',
            'order_number' => 1,
        ]);
        $secondLesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Revenue Dashboards',
            'order_number' => 2,
        ]);

        CourseEnrollment::factory()->completed($secondLesson)->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'started_at' => now()->subDays(5),
        ]);
        CourseEnrollment::factory()->create([
            'user_id' => $dropOffStudent->id,
            'course_id' => $course->id,
            'status' => CourseEnrollmentStatus::Active,
            'started_at' => now()->subDays(4),
            'progress_percent' => 35,
        ]);

        LessonProgress::factory()->completed()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'course_lesson_id' => $firstLesson->id,
            'started_at' => now()->subDays(4),
            'last_watched_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'progress_seconds' => 300,
            'duration_seconds' => 300,
        ]);
        LessonProgress::factory()->completed()->create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'course_lesson_id' => $secondLesson->id,
            'started_at' => now()->subDays(3),
            'last_watched_at' => now()->subDay(),
            'completed_at' => now()->subDay(),
            'progress_seconds' => 420,
            'duration_seconds' => 420,
        ]);
        LessonProgress::factory()->create([
            'user_id' => $dropOffStudent->id,
            'course_id' => $course->id,
            'course_lesson_id' => $firstLesson->id,
            'status' => LessonProgressStatus::InProgress,
            'started_at' => now()->subDays(2),
            'last_watched_at' => now()->subDay(),
            'progress_seconds' => 120,
            'duration_seconds' => 300,
            'progress_percent' => 40,
        ]);

        $product = PaymentProduct::factory()->course($course)->create([
            'name' => 'Industrial Analytics Product',
        ]);
        $price = PaymentPrice::factory()->create([
            'payment_product_id' => $product->id,
            'name' => 'Launch Offer',
            'amount' => 14900,
        ]);
        $order = PaymentOrder::factory()->create([
            'user_id' => $student->id,
            'status' => 'completed',
            'paddle_subscription_id' => 'sub_phase13analytics',
            'subtotal' => 14900,
            'total' => 14900,
            'purchased_at' => now()->subDay(),
        ]);
        PaymentOrderItem::factory()->create([
            'payment_order_id' => $order->id,
            'payment_product_id' => $product->id,
            'payment_price_id' => $price->id,
            'course_id' => $course->id,
            'description' => $course->title,
            'unit_amount' => 14900,
            'total' => 14900,
        ]);

        PaymentReconciliationRecord::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_phase13_reconciled',
            'record_type' => 'transaction',
            'status' => 'reconciled',
            'paddle_transaction_id' => $order->paddle_transaction_id,
            'payload' => [],
            'reconciled_at' => now(),
        ]);
        PaymentWebhookEvent::factory()->create([
            'event_id' => 'evt_phase13_failed',
            'event_type' => 'transaction.completed',
            'status' => 'failed',
            'attempts' => 2,
            'last_error' => 'Signature mismatch',
        ]);

        $post = BlogPost::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Factory automation lessons',
        ]);
        BlogComment::factory()->approved()->create(['blog_post_id' => $post->id]);
        AnalyticsEvent::factory()->type(AnalyticsEventType::BlogView)->create([
            'user_id' => $student->id,
            'blog_post_id' => $post->id,
            'eventable_type' => $post->getMorphClass(),
            'eventable_id' => $post->id,
            'occurred_at' => now()->subDay(),
        ]);
        CreatorAnalyticsSnapshot::factory()->create([
            'user_id' => $author->id,
            'period_date' => today(),
            'blog_views' => 320,
            'course_views' => 140,
            'lesson_views' => 90,
            'comments_count' => 12,
            'enrollments_count' => 8,
            'course_revenue_cents' => 14900,
            'engagement_score' => 88,
        ]);
        CohortRetentionSnapshot::factory()->create([
            'cohort_month' => today()->startOfMonth(),
            'period_number' => 0,
            'users_count' => 2,
            'retained_users_count' => 2,
            'retention_rate_basis_points' => 10000,
        ]);
        AnalyticsFunnelSnapshot::factory()->create([
            'stage' => AnalyticsEventType::LandingPageView->value,
            'stage_order' => 1,
            'visitors_count' => 80,
            'users_count' => 60,
            'conversions_count' => 50,
            'conversion_rate_basis_points' => 6250,
        ]);
        AnalyticsFunnelSnapshot::factory()->create([
            'stage' => AnalyticsEventType::CheckoutCompleted->value,
            'stage_order' => 4,
            'visitors_count' => 20,
            'users_count' => 18,
            'conversions_count' => 12,
            'conversion_rate_basis_points' => 6000,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Reports')
                ->where('reports.studentEngagement.activeStudents', 2)
                ->where('reports.studentEngagement.lessonCompletions', 2)
                ->where('reports.completionDropOff.courses.0.course', 'Industrial Analytics Course')
                ->where('reports.commerceRevenue.totalCents', 14900)
                ->where('reports.commerceRevenue.byCourse.0.revenueCents', 14900)
                ->where('reports.commerceRevenue.byPlan.0.plan', 'Industrial Analytics Product / Launch Offer')
                ->where('reports.commerceRevenue.bySubscription.0.subscription', 'sub_phase13analytics')
                ->where('reports.paddleReconciliation.reconciled', 1)
                ->where('reports.paddleReconciliation.failedEvents', 1)
                ->where('reports.cohortRetention.source', 'snapshots')
                ->where('reports.funnel.source', 'snapshots')
                ->where('reports.authorPerformance.authors.0.author', 'Expert Author')
                ->where('reports.authorPerformance.blogs.0.title', 'Factory automation lessons'),
            );
    }

    public function test_checkout_webhook_and_lesson_completion_emit_funnel_events(): void
    {
        $student = User::factory()->create();
        $student->assignRole(RoleName::Student->value);

        $course = Course::factory()->published()->create(['is_free' => false]);
        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'content' => 'Premium analytics lesson.',
            'is_free' => false,
            'is_paid' => true,
        ]);
        $product = PaymentProduct::factory()->course($course)->create();
        $price = PaymentPrice::factory()->create([
            'payment_product_id' => $product->id,
            'amount' => 9900,
            'paddle_price_id' => 'pri_01phase13course000000000',
        ]);

        Http::fake([
            'https://sandbox-api.paddle.com/transactions*' => Http::response([
                'data' => [
                    'id' => 'txn_01phase13course00000000',
                    'checkout' => [
                        'url' => 'https://pay.scratch.test/checkout?_ptxn=txn_01phase13course00000000',
                    ],
                ],
            ], 201),
        ]);

        $this->actingAs($student)
            ->postJson(route('checkout.courses.store', $course->slug), [
                'visitor_id' => 'visitor-phase-13',
            ])
            ->assertCreated();

        $checkout = PaymentCheckout::query()->firstOrFail();

        $this->assertDatabaseHas(AnalyticsEvent::class, [
            'event_type' => AnalyticsEventType::CheckoutStarted->value,
            'user_id' => $student->id,
            'visitor_id' => 'visitor-phase-13',
            'course_id' => $course->id,
            'payment_checkout_id' => $checkout->id,
        ]);

        $payload = $this->transactionPayload(
            'evt_phase13_course_paid',
            'txn_01phase13course00000000',
            $checkout->custom_data,
            $price,
        );

        $this->postPaddleWebhook($payload)->assertOk();

        $order = PaymentOrder::query()->firstOrFail();

        $this->assertDatabaseHas(AnalyticsEvent::class, [
            'event_type' => AnalyticsEventType::CheckoutCompleted->value,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'payment_checkout_id' => $checkout->id,
            'payment_order_id' => $order->id,
        ]);

        $this->actingAs($student)
            ->postJson(route('student.lessons.progress.store', $lesson->id), [
                'progress_seconds' => 300,
                'duration_seconds' => 300,
                'completed' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', LessonProgressStatus::Completed->value);

        $this->assertDatabaseHas(AnalyticsEvent::class, [
            'event_type' => AnalyticsEventType::LessonCompleted->value,
            'user_id' => $student->id,
            'course_id' => $course->id,
            'course_lesson_id' => $lesson->id,
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
                'customer_id' => 'ctm_01phase13000000000000000',
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
            'HTTP_PADDLE_SIGNATURE' => $this->paddleSignature($body, 'phase13_secret'),
            'REMOTE_ADDR' => '198.51.100.93',
        ], $body);
    }

    private function paddleSignature(string $payload, string $secret): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        return "ts={$timestamp};h1={$signature}";
    }
}
