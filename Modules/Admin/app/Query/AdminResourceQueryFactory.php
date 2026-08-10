<?php

namespace Modules\Admin\Query;

use App\Models\AbandonedCheckoutRecovery;
use App\Models\AbExperiment;
use App\Models\AbVariant;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPricingSetting;
use App\Models\AdvertiserRequest;
use App\Models\AdZone;
use App\Models\AffiliatePartner;
use App\Models\AffiliateVisit;
use App\Models\ApplicationLogEntry;
use App\Models\ApprovalHistory;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\AuditLog;
use App\Models\AuthorBadge;
use App\Models\BlockedWord;
use App\Models\BlogCategory;
use App\Models\BlogComment;
use App\Models\BlogFaq;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Certificate;
use App\Models\CommunityGroup;
use App\Models\CommunityGroupMember;
use App\Models\CommunityReaction;
use App\Models\CommunityReputationScore;
use App\Models\ContentBlock;
use App\Models\ContentReport;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseCategory;
use App\Models\CourseEnrollment;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CoursePurchase;
use App\Models\CourseQuestion;
use App\Models\CourseResource;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\CreatorAgreementAcceptance;
use App\Models\DatabaseBackup;
use App\Models\DiscussionForum;
use App\Models\DiscussionPost;
use App\Models\DiscussionThread;
use App\Models\EditorialRevision;
use App\Models\FailedJob;
use App\Models\GiftPurchase;
use App\Models\HealthCheckRun;
use App\Models\HomeHeroSlide;
use App\Models\HomePageSection;
use App\Models\InstructorProfile;
use App\Models\LeadMagnet;
use App\Models\LeadSubmission;
use App\Models\LearningPath;
use App\Models\LessonBookmark;
use App\Models\LessonDripSchedule;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\LessonQuestionAnswer;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\MediaUsage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\ModerationQueueItem;
use App\Models\NewsletterCampaign;
use App\Models\OperationalAlert;
use App\Models\Page;
use App\Models\PaymentAuditLog;
use App\Models\PaymentCheckout;
use App\Models\PaymentDiscount;
use App\Models\PaymentDiscountRedemption;
use App\Models\PaymentEntitlement;
use App\Models\PaymentOrder;
use App\Models\PaymentOrderItem;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\PaymentReconciliationRecord;
use App\Models\PaymentSubscription;
use App\Models\PaymentWebhookEvent;
use App\Models\QueueJob;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizQuestion;
use App\Models\ReferralConversion;
use App\Models\ReputationEvent;
use App\Models\RevenueShareRule;
use App\Models\ReviewerComment;
use App\Models\ScheduledPublication;
use App\Models\SchedulerHeartbeat;
use App\Models\SiteSetting;
use App\Models\SkillTrack;
use App\Models\SocialShareImage;
use App\Models\TeamAccount;
use App\Models\TeamSeat;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class AdminResourceQueryFactory
{
    /**
     * @return Builder<covariant Model>
     */
    public function make(string $resource): Builder
    {
        return match ($resource) {
            'users' => User::query()->with('roles')->latest(),
            'roles' => Role::query()->with('permissions')->orderBy('name'),
            'permissions' => Permission::query()->orderBy('name'),
            'bloggers' => BloggerProfile::query()->with(['user.roles', 'reviewer', 'approvalHistories.actor'])->latest(),
            'course_categories' => CourseCategory::query()
                ->withCount(['courses', 'subcategories'])
                ->orderBy('sort_order')
                ->orderBy('name'),
            'course_subcategories' => CourseSubcategory::query()
                ->with('category')
                ->withCount('courses')
                ->orderBy('sort_order')
                ->orderBy('name'),
            'courses' => Course::query()
                ->with(['category', 'subcategory', 'thumbnail', 'creator.bloggerProfile', 'ownershipVideo', 'approvalHistories.actor', 'deletionRequests'])
                ->withCount(['sections', 'lessons', 'resources', 'faqs'])
                ->latest(),
            'course_sections' => CourseSection::query()
                ->with('course')
                ->withCount('lessons')
                ->orderBy('sort_order')
                ->orderBy('title'),
            'course_resources' => CourseResource::query()
                ->with(['course', 'lesson', 'mediaAsset'])
                ->orderBy('sort_order')
                ->orderBy('title'),
            'course_faqs' => CourseFaq::query()
                ->with(['course', 'lesson'])
                ->orderBy('sort_order')
                ->orderBy('question'),
            'lessons' => CourseLesson::query()->with(['course', 'section', 'videoFile', 'approvalHistories.actor'])->latest(),
            'course_enrollments' => CourseEnrollment::query()->with(['user', 'course', 'lastLesson'])->latest(),
            'learning_paths' => LearningPath::query()->with('courses')->withCount(['courses', 'students'])->orderBy('sort_order')->latest(),
            'course_bundles' => CourseBundle::query()->with('courses')->withCount(['courses', 'students'])->orderBy('sort_order')->latest(),
            'skill_tracks' => SkillTrack::query()->with('courses')->withCount('courses')->orderBy('sort_order')->latest(),
            'drip_schedules' => LessonDripSchedule::query()->with(['course', 'lesson'])->latest(),
            'quizzes' => Quiz::query()->with(['course', 'lesson'])->withCount(['questions', 'attempts'])->orderBy('sort_order')->latest(),
            'quiz_questions' => QuizQuestion::query()->with('quiz.course')->orderBy('quiz_id')->orderBy('sort_order'),
            'quiz_attempts' => QuizAttempt::query()->with(['user', 'quiz', 'course', 'lesson'])->latest(),
            'assignments' => Assignment::query()->with(['course', 'lesson'])->withCount('submissions')->orderBy('sort_order')->latest(),
            'assignment_submissions' => AssignmentSubmission::query()->with(['assignment', 'user', 'course', 'lesson', 'mediaAsset', 'grader'])->latest(),
            'certificates' => Certificate::query()->with(['user', 'course'])->latest('issued_at'),
            'lesson_progress' => LessonProgress::query()->with(['user', 'course', 'lesson'])->latest('last_watched_at'),
            'lesson_notes' => LessonNote::query()->with(['user', 'course', 'lesson'])->latest(),
            'lesson_bookmarks' => LessonBookmark::query()->with(['user', 'course', 'lesson'])->latest('saved_at'),
            'blogs' => BlogPost::query()->with(['category', 'author', 'featuredImage', 'tags', 'approvalHistories.actor', 'deletionRequests'])->latest(),
            'blog_categories' => BlogCategory::query()->withCount('posts')->orderBy('name'),
            'blog_tags' => BlogTag::query()->withCount('posts')->orderBy('name'),
            'blog_faqs' => BlogFaq::query()->with('post')->orderBy('sort_order')->orderBy('question'),
            'blog_comments' => BlogComment::query()->with(['post', 'user', 'parent'])->latest(),
            'cms' => Page::query()->with(['author', 'approvalHistories.actor'])->latest(),
            'content_blocks' => ContentBlock::query()->with('page')->orderBy('sort_order')->orderBy('key'),
            'home_hero' => SiteSetting::query()->where('key', 'home.hero')->latest(),
            'home_hero_slides' => HomeHeroSlide::query()->with('mediaAsset')->orderBy('sort_order')->latest(),
            'home_page_sections' => HomePageSection::query()->orderBy('sort_order')->latest(),
            'menus' => Menu::query()->withCount('items')->latest(),
            'menu_items' => MenuItem::query()->with(['menu', 'parent', 'page'])->withCount('children')->orderBy('menu_id')->orderBy('sort_order'),
            'media' => MediaAsset::query()->with(['folder', 'uploader', 'usages.mediable'])->withCount('usages')->latest(),
            'media_folders' => MediaFolder::query()->with('parent')->withCount(['children', 'assets'])->orderBy('path')->orderBy('name'),
            'media_usages' => MediaUsage::query()->with(['asset', 'mediable'])->latest(),
            'creator_agreements' => CreatorAgreementAcceptance::query()->with('user')->latest('accepted_at'),
            'ad_zones' => AdZone::query()->withCount(['creatives', 'impressions', 'clicks'])->latest(),
            'ad_campaigns' => AdCampaign::query()->withCount(['creatives', 'reports'])->latest(),
            'ad_creatives' => AdCreative::query()->with(['campaign', 'zone', 'mediaAsset'])->withCount(['impressions', 'clicks'])->latest(),
            'advertiser_requests' => AdvertiserRequest::query()->with(['requestedZone', 'reviewer'])->latest(),
            'ad_pricing' => AdPricingSetting::query()->with('zone')->latest(),
            'instructors' => InstructorProfile::query()->with(['user', 'reviewer', 'avatar', 'approvalHistories.actor'])->latest(),
            'author_badges' => AuthorBadge::query()->withCount('users')->orderBy('sort_order')->latest(),
            'editorial_revisions' => EditorialRevision::query()->with(['author', 'reviewer', 'comments', 'editorialable', 'approvalHistories.actor'])->withCount('comments')->latest(),
            'reviewer_comments' => ReviewerComment::query()->with(['revision', 'reviewer', 'resolver'])->latest(),
            'scheduled_publications' => ScheduledPublication::query()->with(['revision', 'creator', 'approver'])->latest('publish_at'),
            'revenue_share_rules' => RevenueShareRule::query()->with(['user', 'instructorProfile', 'course', 'paymentProduct'])->latest(),
            'payment_products' => PaymentProduct::query()->with(['course', 'bundle', 'prices'])->withCount('prices')->latest(),
            'payment_prices' => PaymentPrice::query()->with('product.course')->latest(),
            'payment_discounts' => PaymentDiscount::query()->with(['product', 'price', 'course'])->withCount('redemptions')->latest(),
            'payment_checkouts' => PaymentCheckout::query()->with(['user', 'price.product', 'course', 'teamAccount', 'discount'])->latest(),
            'payment_orders' => PaymentOrder::query()->with(['user', 'teamAccount', 'checkout', 'items.product', 'items.price'])->latest(),
            'payment_subscriptions' => PaymentSubscription::query()->with(['user', 'teamAccount', 'product', 'price'])->latest(),
            'team_accounts' => TeamAccount::query()->with(['owner'])->withCount(['seats', 'entitlements'])->latest(),
            'team_seats' => TeamSeat::query()->with(['teamAccount', 'user'])->latest(),
            'payment_reconciliation' => PaymentReconciliationRecord::query()->latest(),
            'course_purchases' => CoursePurchase::query()->with(['user', 'course', 'order'])->latest('purchased_at'),
            'payment_entitlements' => PaymentEntitlement::query()->with(['user', 'teamAccount', 'entitlementable', 'order', 'subscription'])->latest(),
            'payment_order_items' => PaymentOrderItem::query()->with(['order.user', 'product', 'price', 'course'])->latest(),
            'discount_redemptions' => PaymentDiscountRedemption::query()->with(['discount', 'user', 'checkout', 'order'])->latest('redeemed_at'),
            'payment_webhook_events' => PaymentWebhookEvent::query()->latest(),
            'payment_audit_logs' => PaymentAuditLog::query()->with(['actor', 'user', 'auditable'])->latest(),
            'gift_purchases' => GiftPurchase::query()->with(['purchaser', 'recipient', 'course', 'bundle', 'product', 'price', 'checkout'])->latest(),
            'affiliate_partners' => AffiliatePartner::query()->with('user')->withCount(['visits', 'conversions'])->latest(),
            'affiliate_visits' => AffiliateVisit::query()->with(['partner', 'conversions'])->latest('clicked_at'),
            'referral_conversions' => ReferralConversion::query()->with(['partner', 'checkout', 'order', 'user'])->latest(),
            'checkout_recoveries' => AbandonedCheckoutRecovery::query()->with(['checkout.price.product', 'checkout.user'])->latest(),
            'lead_magnets' => LeadMagnet::query()->with('asset')->withCount('submissions')->latest(),
            'newsletter_campaigns' => NewsletterCampaign::query()->with('leadMagnet')->withCount('submissions')->latest(),
            'lead_submissions' => LeadSubmission::query()->with(['leadMagnet', 'newsletterCampaign', 'user'])->latest(),
            'ab_experiments' => AbExperiment::query()->withCount('variants')->latest(),
            'ab_variants' => AbVariant::query()->with('experiment')->withCount('assignments')->latest(),
            'social_share_images' => SocialShareImage::query()->with('media')->latest(),
            'moderation_queue' => ModerationQueueItem::query()->with(['reporter', 'assignee'])->latest(),
            'content_reports' => ContentReport::query()->with(['reporter', 'reviewer'])->latest(),
            'blocked_words' => BlockedWord::query()->latest(),
            'discussion_forums' => DiscussionForum::query()->with(['course', 'category', 'creator'])->withCount('threads')->latest(),
            'discussion_threads' => DiscussionThread::query()->with(['forum', 'user'])->withCount('posts')->latest(),
            'community_groups' => CommunityGroup::query()->with(['course', 'creator'])->withCount('members')->latest(),
            'course_questions' => CourseQuestion::query()->with(['course', 'lesson', 'user', 'acceptedAnswer.user'])->withCount('answers')->latest(),
            'lesson_question_answers' => LessonQuestionAnswer::query()->with(['question.course', 'question.lesson', 'user', 'acceptedBy'])->withCount('reactions')->latest(),
            'discussion_posts' => DiscussionPost::query()->with(['thread.forum', 'user', 'parent'])->withCount(['replies', 'reactions'])->latest(),
            'community_reactions' => CommunityReaction::query()->with(['user', 'reactable'])->latest(),
            'reputation_events' => ReputationEvent::query()->with(['user', 'actor', 'subject'])->latest(),
            'community_reputation_scores' => CommunityReputationScore::query()->with('user')->orderByDesc('points'),
            'community_group_members' => CommunityGroupMember::query()->with(['group.course', 'user'])->latest('joined_at'),
            'moderation_history' => ModerationQueueItem::query()->with(['subject', 'reporter', 'assignee'])->whereNotNull('reviewed_at')->latest('reviewed_at'),
            'database_backups' => DatabaseBackup::query()->latest('started_at'),
            'queue_jobs' => QueueJob::query()->latest('id'),
            'failed_jobs' => FailedJob::query()->latest('failed_at'),
            'scheduler_heartbeats' => SchedulerHeartbeat::query()->latest('started_at'),
            'paddle_webhook_health' => PaymentWebhookEvent::query()->where('provider', 'paddle')->latest(),
            'application_logs' => ApplicationLogEntry::query()->latest('occurred_at'),
            'audit_logs' => AuditLog::query()->with(['actor', 'auditable'])->latest(),
            'approval_histories' => ApprovalHistory::query()->with(['actor', 'subject'])->latest(),
            'health_checks' => HealthCheckRun::query()->latest('checked_at'),
            'operational_alerts' => OperationalAlert::query()->with(['acknowledgedBy', 'resolvedBy'])->latest('last_detected_at'),
            'settings' => SiteSetting::query()->latest(),
            default => abort(404),
        };
    }
}
