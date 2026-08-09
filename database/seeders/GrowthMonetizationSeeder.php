<?php

namespace Database\Seeders;

use App\Enums\DiscountType;
use App\Enums\GrowthStatus;
use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\AffiliatePartner;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\LeadMagnet;
use App\Models\MediaAsset;
use App\Models\NewsletterCampaign;
use App\Models\PaymentDiscount;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\SocialShareImage;
use Illuminate\Database\Seeder;

class GrowthMonetizationSeeder extends Seeder
{
    /**
     * Seed starter growth loops that can be safely edited from admin.
     */
    public function run(): void
    {
        $course = Course::query()->where('slug', 'industrial-laravel-foundations')->first();
        $post = BlogPost::query()->where('slug', 'building-industrial-learning-platforms')->first();
        $media = MediaAsset::query()->where('path', 'media/industrial-laravel.jpg')->first();

        $courseProduct = $course
            ? PaymentProduct::query()->where('course_id', $course->id)->first()
            : null;

        if ($courseProduct) {
            PaymentDiscount::query()->firstOrCreate(
                ['code' => 'LAUNCH25'],
                [
                    'payment_product_id' => $courseProduct->id,
                    'name' => 'Launch 25 Percent Offer',
                    'description' => 'Limited launch offer for the flagship course.',
                    'type' => DiscountType::Percent,
                    'value' => 25,
                    'currency' => 'USD',
                    'status' => GrowthStatus::Active,
                    'is_launch_offer' => true,
                    'max_redemptions' => 250,
                    'per_user_limit' => 1,
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonth(),
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        $bundle = CourseBundle::query()->where('slug', 'industrial-learning-platform-bundle')->first();

        if ($bundle) {
            $bundleProduct = PaymentProduct::query()->firstOrCreate(
                ['slug' => 'industrial-learning-platform-bundle'],
                [
                    'course_bundle_id' => $bundle->id,
                    'name' => 'Industrial Learning Platform Bundle',
                    'description' => 'Bundled access to the industrial platform learning track.',
                    'type' => PaymentProductType::Bundle,
                    'status' => PaymentProductStatus::Active,
                    'tax_category' => 'training-services',
                    'metadata' => ['seeded' => true, 'feature' => 'bundle'],
                ],
            );

            PaymentPrice::query()->firstOrCreate(
                ['payment_product_id' => $bundleProduct->id, 'name' => 'Bundle Lifetime Access'],
                [
                    'billing_interval' => PaymentBillingInterval::OneTime,
                    'is_recurring' => false,
                    'currency' => 'USD',
                    'amount' => 29_900,
                    'is_active' => true,
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        AffiliatePartner::query()->firstOrCreate(
            ['code' => 'LAUNCHPARTNER'],
            [
                'name' => 'Launch Partner Program',
                'status' => 'active',
                'commission_rate_basis_points' => 1500,
                'cookie_days' => 45,
                'payout_email' => 'partners@example.com',
                'notes' => 'Seed partner for validating referral tracking.',
                'metadata' => ['seeded' => true],
            ],
        );

        $leadMagnet = LeadMagnet::query()->firstOrCreate(
            ['slug' => 'industrial-lms-launch-checklist'],
            [
                'asset_media_id' => $media?->id,
                'title' => 'Industrial LMS Launch Checklist',
                'description' => 'A practical checklist for launch readiness, payments, content, and operations.',
                'status' => GrowthStatus::Active,
                'form_headline' => 'Get the launch checklist',
                'delivery_url' => '/storage/media/industrial-lms-launch-checklist.pdf',
                'metadata' => ['seeded' => true],
            ],
        );

        NewsletterCampaign::query()->firstOrCreate(
            ['slug' => 'launch-readiness-sequence'],
            [
                'lead_magnet_id' => $leadMagnet->id,
                'name' => 'Launch Readiness Sequence',
                'subject' => 'Your LMS launch checklist is ready',
                'audience' => 'lead_magnet:industrial-lms-launch-checklist',
                'status' => GrowthStatus::Draft,
                'scheduled_at' => now()->addDays(2),
                'metadata' => ['seeded' => true],
            ],
        );

        $experiment = AbExperiment::query()->firstOrCreate(
            ['key' => 'pricing_cta'],
            [
                'name' => 'Pricing CTA Test',
                'surface' => 'course_detail_pricing',
                'status' => GrowthStatus::Active,
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addMonth(),
                'metadata' => ['seeded' => true],
            ],
        );

        foreach ([
            'control' => ['name' => 'Start Learning', 'weight' => 50, 'payload' => ['cta' => 'Start learning']],
            'annual_push' => ['name' => 'Save With Annual', 'weight' => 50, 'payload' => ['cta' => 'Save with annual access']],
        ] as $key => $variant) {
            AbVariant::query()->firstOrCreate(
                ['ab_experiment_id' => $experiment->id, 'key' => $key],
                [
                    'name' => $variant['name'],
                    'weight' => $variant['weight'],
                    'payload' => $variant['payload'],
                ],
            );
        }

        if ($course) {
            $this->shareImage($course, $media, 'Industrial Laravel Foundations Social Preview');
        }

        if ($post) {
            $this->shareImage($post, $media, 'Industrial Learning Platforms Social Preview');
        }
    }

    private function shareImage(Course|BlogPost $model, ?MediaAsset $media, string $title): void
    {
        SocialShareImage::query()->firstOrCreate(
            [
                'shareable_type' => $model->getMorphClass(),
                'shareable_id' => $model->id,
                'template' => 'industrial-default',
            ],
            [
                'media_asset_id' => $media?->id,
                'image_url' => $media?->url,
                'title' => $title,
                'alt_text' => $title,
                'status' => GrowthStatus::Active,
                'metadata' => ['seeded' => true],
            ],
        );
    }
}
