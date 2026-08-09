<?php

namespace Database\Seeders;

use App\Enums\EditorialRevisionStatus;
use App\Enums\InstructorProfileStatus;
use App\Enums\RevenueShareRuleStatus;
use App\Enums\RevenueShareRuleType;
use App\Enums\ScheduledPublicationStatus;
use App\Models\AuthorBadge;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CreatorAnalyticsSnapshot;
use App\Models\EditorialRevision;
use App\Models\InstructorProfile;
use App\Models\RevenueShareRule;
use App\Models\ScheduledPublication;
use App\Models\User;
use Illuminate\Database\Seeder;

class CreatorEditorialSeeder extends Seeder
{
    /**
     * Seed starter instructor, editorial, analytics, badge, and revenue-share records.
     */
    public function run(): void
    {
        $user = User::query()->where('email', 'content.manager@example.com')->first();

        if (! $user) {
            return;
        }

        $profile = InstructorProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'display_name' => $user->name,
                'slug' => 'content-manager',
                'headline' => 'Industrial Laravel instructor',
                'bio' => 'Builds practical learning paths for production-grade Laravel teams.',
                'credentials' => 'Platform engineering, Laravel architecture, editorial training operations.',
                'expertise' => 'Laravel Platforms',
                'status' => InstructorProfileStatus::Approved,
                'is_verified_expert' => true,
                'accepts_revenue_share' => true,
                'reviewed_at' => now(),
                'payout_currency' => 'USD',
                'metadata' => ['seeded' => true],
            ],
        );

        $badge = AuthorBadge::query()->firstOrCreate(
            ['slug' => 'verified-expert'],
            [
                'name' => 'Verified Expert',
                'description' => 'Reviewed and approved as a verified subject-matter expert.',
                'icon' => 'badge-check',
                'color' => 'teal',
                'marks_verified_expert' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $badge->users()->syncWithoutDetaching([
            $user->id => [
                'awarded_by' => $user->id,
                'awarded_at' => now(),
                'notes' => 'Seeded verified expert badge.',
            ],
        ]);

        CreatorAnalyticsSnapshot::query()->updateOrCreate(
            ['user_id' => $user->id, 'period_date' => today(), 'period' => 'daily'],
            [
                'instructor_profile_id' => $profile->id,
                'blog_views' => 240,
                'course_views' => 180,
                'lesson_views' => 430,
                'comments_count' => 12,
                'enrollments_count' => 18,
                'course_revenue_cents' => 14900,
                'ad_revenue_cents' => 1200,
                'engagement_score' => 74,
                'metadata' => ['source' => 'seed'],
            ],
        );

        $post = BlogPost::query()->where('slug', 'building-industrial-learning-platforms')->first();

        if ($post) {
            $revision = EditorialRevision::query()->firstOrCreate(
                ['editorialable_type' => $post->getMorphClass(), 'editorialable_id' => $post->id, 'title' => 'Editorial polish pass'],
                [
                    'author_id' => $user->id,
                    'reviewer_id' => $user->id,
                    'summary' => 'Seeded revision showing reviewer workflow.',
                    'payload' => ['excerpt' => $post->excerpt],
                    'status' => EditorialRevisionStatus::Approved,
                    'submitted_at' => now()->subHours(2),
                    'reviewed_at' => now()->subHour(),
                ],
            );

            ScheduledPublication::query()->firstOrCreate(
                ['publishable_type' => $post->getMorphClass(), 'publishable_id' => $post->id, 'editorial_revision_id' => $revision->id],
                [
                    'created_by' => $user->id,
                    'approved_by' => $user->id,
                    'publish_at' => now()->addDay(),
                    'timezone' => config('app.timezone', 'UTC'),
                    'status' => ScheduledPublicationStatus::Scheduled,
                    'metadata' => ['seeded' => true],
                ],
            );
        }

        $course = Course::query()->where('slug', 'industrial-laravel-foundations')->first();

        if ($course) {
            RevenueShareRule::query()->firstOrCreate(
                ['user_id' => $user->id, 'course_id' => $course->id],
                [
                    'instructor_profile_id' => $profile->id,
                    'type' => RevenueShareRuleType::Course,
                    'status' => RevenueShareRuleStatus::Active,
                    'share_percent' => 35,
                    'currency' => 'USD',
                    'starts_at' => now()->subDay(),
                    'notes' => 'Prepared revenue-share rule for paid instructor payouts.',
                    'metadata' => ['payout_model' => 'future_reconciliation'],
                ],
            );
        }
    }
}
