<?php

namespace App\Services\Analytics;

use App\Enums\AnalyticsEventType;
use App\Models\AnalyticsEvent;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\LessonProgress;
use App\Models\PaymentCheckout;
use App\Models\PaymentOrder;
use App\Models\User;
use App\Services\Growth\AffiliateTrackingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsEventService
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function trackRequest(AnalyticsEventType $type, Request $request, ?Model $eventable = null, array $metadata = []): AnalyticsEvent
    {
        return $this->track($type, [
            'user' => $request->user(),
            'session_id' => $request->hasSession() ? $request->session()->getId() : null,
            'visitor_id' => $this->visitorIdFromRequest($request),
            'eventable' => $eventable,
            'url' => $request->fullUrl(),
            'referrer_url' => $request->headers->get('referer'),
            'source' => $this->sourceFromRequest($request),
            'metadata' => $metadata,
        ]);
    }

    public function trackCourseView(Request $request, Course $course): AnalyticsEvent
    {
        return $this->trackRequest(AnalyticsEventType::CourseView, $request, $course, [
            'course_slug' => $course->slug,
            'category_id' => $course->course_category_id,
        ]);
    }

    public function trackBlogView(Request $request, BlogPost $post): AnalyticsEvent
    {
        return $this->trackRequest(AnalyticsEventType::BlogView, $request, $post, [
            'blog_slug' => $post->slug,
            'category_id' => $post->blog_category_id,
        ]);
    }

    public function trackCheckoutStarted(PaymentCheckout $checkout, ?Request $request = null): AnalyticsEvent
    {
        $checkout->loadMissing(['user', 'course', 'price.product']);

        return $this->track(AnalyticsEventType::CheckoutStarted, [
            'user' => $checkout->user,
            'session_id' => $request?->hasSession() ? $request->session()->getId() : null,
            'visitor_id' => $request ? $this->visitorIdFromRequest($request) : $this->visitorIdFromCheckout($checkout),
            'eventable' => $checkout,
            'course' => $checkout->course,
            'payment_checkout' => $checkout,
            'url' => $request?->fullUrl(),
            'referrer_url' => $request?->headers->get('referer'),
            'source' => 'checkout',
            'metadata' => [
                'payment_price_id' => $checkout->payment_price_id,
                'payment_product_id' => $checkout->price?->payment_product_id,
                'product_type' => $checkout->price?->product ? $this->enumValue($checkout->price->product->type) : null,
                'discount_amount' => $checkout->discount_amount,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function trackCheckoutCompleted(PaymentCheckout $checkout, PaymentOrder $order, array $context = []): AnalyticsEvent
    {
        $checkout->loadMissing(['user', 'course', 'price.product']);

        return $this->track(AnalyticsEventType::CheckoutCompleted, [
            'user' => $checkout->user,
            'visitor_id' => $this->visitorIdFromCheckout($checkout),
            'eventable' => $order,
            'course' => $checkout->course,
            'payment_checkout' => $checkout,
            'payment_order' => $order,
            'source' => 'paddle_webhook',
            'metadata' => [
                'currency' => $order->currency,
                'total' => $order->total,
                'payment_price_id' => $checkout->payment_price_id,
                'payment_product_id' => $checkout->price?->payment_product_id,
                'product_type' => $context['product_type'] ?? null,
            ],
        ]);
    }

    public function trackLessonProgress(User $user, CourseLesson $lesson, LessonProgress $progress): AnalyticsEvent
    {
        $lesson->loadMissing('course');
        $isCompleted = $this->enumValue($progress->getAttribute('status')) === 'completed';

        return $this->track($isCompleted ? AnalyticsEventType::LessonCompleted : AnalyticsEventType::LessonProgress, [
            'user' => $user,
            'eventable' => $lesson,
            'course' => $lesson->course,
            'lesson' => $lesson,
            'source' => 'student_learning',
            'metadata' => [
                'progress_percent' => $progress->progress_percent,
                'progress_seconds' => $progress->progress_seconds,
                'duration_seconds' => $progress->duration_seconds,
            ],
        ]);
    }

    /**
     * @param  array{
     *     user?: mixed,
     *     session_id?: string|null,
     *     visitor_id?: string|null,
     *     eventable?: Model|null,
     *     course?: Course|null,
     *     lesson?: CourseLesson|null,
     *     post?: BlogPost|null,
     *     payment_checkout?: PaymentCheckout|null,
     *     payment_order?: PaymentOrder|null,
     *     url?: string|null,
     *     referrer_url?: string|null,
     *     source?: string|null,
     *     metadata?: array<string, mixed>
     * }  $payload
     */
    private function track(AnalyticsEventType $type, array $payload): AnalyticsEvent
    {
        $eventable = $payload['eventable'] ?? null;
        $course = $payload['course'] ?? ($eventable instanceof Course ? $eventable : null);
        $lesson = $payload['lesson'] ?? ($eventable instanceof CourseLesson ? $eventable : null);
        $post = $payload['post'] ?? ($eventable instanceof BlogPost ? $eventable : null);
        $checkout = $payload['payment_checkout'] ?? ($eventable instanceof PaymentCheckout ? $eventable : null);
        $order = $payload['payment_order'] ?? ($eventable instanceof PaymentOrder ? $eventable : null);
        $user = $payload['user'] ?? null;

        return AnalyticsEvent::query()->create([
            'user_id' => $user instanceof User ? $user->id : null,
            'session_id' => $payload['session_id'] ?? null,
            'visitor_id' => $payload['visitor_id'] ?? null,
            'event_type' => $type,
            'event_name' => $type->value,
            'eventable_type' => $eventable?->getMorphClass(),
            'eventable_id' => $eventable?->getKey(),
            'course_id' => $course?->id ?: $lesson?->course_id,
            'course_lesson_id' => $lesson?->id,
            'blog_post_id' => $post?->id,
            'payment_checkout_id' => $checkout?->id,
            'payment_order_id' => $order?->id,
            'url' => $payload['url'] ?? null,
            'referrer_url' => $payload['referrer_url'] ?? null,
            'source' => $payload['source'] ?? null,
            'occurred_at' => now(),
            'metadata' => $payload['metadata'] ?? [],
        ]);
    }

    private function visitorIdFromRequest(Request $request): string
    {
        $visitorId = $request->string('visitor_id')->toString();
        $cookieVisitorId = $request->cookie(AffiliateTrackingService::VISITOR_COOKIE);

        if (blank($visitorId) && is_string($cookieVisitorId)) {
            $visitorId = $cookieVisitorId;
        }

        return filled($visitorId) ? Str::limit($visitorId, 64, '') : (string) Str::uuid();
    }

    private function visitorIdFromCheckout(PaymentCheckout $checkout): ?string
    {
        $rawCustomData = $checkout->getAttribute('custom_data');
        $customData = is_array($rawCustomData) ? $rawCustomData : [];
        $visitorId = data_get($customData, 'growth.affiliate.visitor_id') ?: data_get($customData, 'growth.ab_test.visitor_id');

        return is_string($visitorId) && filled($visitorId) ? Str::limit($visitorId, 64, '') : null;
    }

    private function sourceFromRequest(Request $request): ?string
    {
        $source = $request->query('utm_source');

        return is_string($source) && filled($source) ? Str::limit($source, 128, '') : null;
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }
}
