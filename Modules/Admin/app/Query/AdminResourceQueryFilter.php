<?php

namespace Modules\Admin\Query;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class AdminResourceQueryFilter
{
    /** @var list<string> */
    private const STATUS_RESOURCES = [
        'users', 'bloggers', 'courses', 'course_sections', 'course_faqs', 'lessons', 'blogs', 'blog_faqs', 'blog_comments', 'cms', 'ad_zones',
        'ad_campaigns', 'ad_creatives', 'advertiser_requests', 'instructors',
        'editorial_revisions', 'scheduled_publications', 'revenue_share_rules',
        'payment_products', 'payment_discounts', 'payment_checkouts', 'payment_orders',
        'payment_subscriptions', 'team_accounts', 'team_seats', 'payment_reconciliation',
        'gift_purchases', 'affiliate_partners', 'referral_conversions',
        'checkout_recoveries', 'lead_magnets', 'newsletter_campaigns', 'lead_submissions',
        'ab_experiments', 'social_share_images', 'moderation_queue', 'content_reports',
        'discussion_forums', 'discussion_threads', 'community_groups',
        'course_enrollments', 'learning_paths', 'course_bundles', 'skill_tracks',
        'quiz_attempts', 'assignment_submissions', 'certificates', 'lesson_progress',
        'course_purchases', 'payment_entitlements', 'payment_webhook_events',
        'course_questions', 'lesson_question_answers', 'discussion_posts',
        'community_group_members', 'moderation_history',
        'database_backups', 'scheduler_heartbeats', 'paddle_webhook_health',
        'health_checks', 'operational_alerts',
    ];

    /** @var list<string> */
    private const ACTIVE_RESOURCES = [
        'course_categories', 'course_subcategories', 'course_resources', 'blog_categories', 'content_blocks', 'menu_items', 'ad_pricing',
        'payment_prices', 'author_badges', 'blocked_words', 'home_page_sections',
        'drip_schedules', 'quizzes', 'assignments',
    ];

    /** @var array<string, string> */
    private const CATEGORY_COLUMNS = [
        'courses' => 'course_category_id',
        'course_subcategories' => 'course_category_id',
        'course_sections' => 'course_id',
        'course_resources' => 'course_id',
        'course_faqs' => 'course_id',
        'blogs' => 'blog_category_id',
        'blog_faqs' => 'blog_post_id',
        'blog_comments' => 'blog_post_id',
        'content_blocks' => 'page_id',
        'menu_items' => 'menu_id',
        'media_folders' => 'parent_id',
        'media_usages' => 'media_asset_id',
        'creator_agreements' => 'user_id',
        'lessons' => 'course_id',
        'course_enrollments' => 'course_id',
        'drip_schedules' => 'course_id',
        'quizzes' => 'course_id',
        'quiz_questions' => 'quiz_id',
        'quiz_attempts' => 'course_id',
        'assignments' => 'course_id',
        'assignment_submissions' => 'assignment_id',
        'certificates' => 'course_id',
        'lesson_progress' => 'course_id',
        'lesson_notes' => 'course_id',
        'lesson_bookmarks' => 'course_id',
        'ad_creatives' => 'ad_zone_id',
        'ad_pricing' => 'ad_zone_id',
        'advertiser_requests' => 'requested_ad_zone_id',
        'editorial_revisions' => 'author_id',
        'reviewer_comments' => 'reviewer_id',
        'scheduled_publications' => 'created_by',
        'revenue_share_rules' => 'course_id',
        'payment_products' => 'type',
        'payment_discounts' => 'payment_product_id',
        'payment_prices' => 'payment_product_id',
        'payment_checkouts' => 'payment_price_id',
        'payment_subscriptions' => 'payment_product_id',
        'team_seats' => 'team_account_id',
        'gift_purchases' => 'payment_product_id',
        'affiliate_visits' => 'affiliate_partner_id',
        'referral_conversions' => 'affiliate_partner_id',
        'newsletter_campaigns' => 'lead_magnet_id',
        'lead_submissions' => 'lead_magnet_id',
        'ab_variants' => 'ab_experiment_id',
        'discussion_forums' => 'course_id',
        'community_groups' => 'course_id',
        'discussion_threads' => 'discussion_forum_id',
        'course_purchases' => 'course_id',
        'payment_entitlements' => 'type',
        'payment_order_items' => 'payment_order_id',
        'discount_redemptions' => 'payment_discount_id',
        'payment_webhook_events' => 'event_type',
        'payment_audit_logs' => 'action',
        'course_questions' => 'course_id',
        'lesson_question_answers' => 'course_question_id',
        'discussion_posts' => 'discussion_thread_id',
        'community_group_members' => 'community_group_id',
        'database_backups' => 'driver',
        'queue_jobs' => 'queue',
        'failed_jobs' => 'queue',
        'scheduler_heartbeats' => 'name',
        'paddle_webhook_health' => 'event_type',
        'application_logs' => 'channel',
        'audit_logs' => 'action',
        'approval_histories' => 'decision',
        'health_checks' => 'check_name',
        'operational_alerts' => 'source',
    ];

    /** @var array<string, list<string>> */
    private const SEARCH_COLUMNS = [
        'users' => ['name', 'email'],
        'roles' => ['name'],
        'permissions' => ['name'],
        'courses' => ['title', 'slug'],
        'course_categories' => ['name', 'slug', 'description'],
        'course_subcategories' => ['name', 'slug', 'description'],
        'course_sections' => ['title', 'slug', 'description'],
        'course_resources' => ['title', 'description', 'type', 'external_url'],
        'course_faqs' => ['question', 'answer'],
        'lessons' => ['title', 'slug'],
        'course_enrollments' => ['source'],
        'learning_paths' => ['title', 'slug', 'description'],
        'course_bundles' => ['title', 'slug', 'description'],
        'skill_tracks' => ['title', 'slug', 'description'],
        'drip_schedules' => ['release_type'],
        'quizzes' => ['title', 'description'],
        'quiz_questions' => ['question', 'explanation'],
        'assignments' => ['title', 'instructions'],
        'certificates' => ['certificate_number', 'verification_code'],
        'lesson_notes' => ['body'],
        'lesson_bookmarks' => ['label'],
        'blogs' => ['title', 'slug'],
        'blog_categories' => ['name', 'slug', 'description'],
        'blog_tags' => ['name', 'slug'],
        'blog_faqs' => ['question', 'answer'],
        'blog_comments' => ['body'],
        'cms' => ['title', 'slug'],
        'content_blocks' => ['key', 'title', 'body'],
        'home_hero' => ['group', 'key'],
        'home_hero_slides' => ['title', 'eyebrow', 'subtitle', 'target_url'],
        'home_page_sections' => ['key', 'type', 'eyebrow', 'title', 'subtitle'],
        'menus' => ['name', 'location'],
        'menu_items' => ['title', 'url', 'route_name'],
        'media' => ['title', 'path', 'alt_text'],
        'media_folders' => ['name', 'slug', 'path'],
        'media_usages' => ['collection', 'mediable_type'],
        'creator_agreements' => ['terms_version', 'ip_address', 'user_agent'],
        'settings' => ['group', 'key'],
        'ad_zones' => ['name', 'location', 'slug'],
        'ad_campaigns' => ['name', 'advertiser_name', 'advertiser_email'],
        'ad_creatives' => ['name', 'headline'],
        'advertiser_requests' => ['company_name', 'contact_name', 'email'],
        'ad_pricing' => ['name', 'currency'],
        'instructors' => ['display_name', 'headline', 'expertise'],
        'author_badges' => ['name', 'slug'],
        'editorial_revisions' => ['title', 'summary'],
        'reviewer_comments' => ['body', 'field_path'],
        'scheduled_publications' => ['timezone', 'failure_reason'],
        'revenue_share_rules' => ['notes'],
        'payment_products' => ['name', 'slug', 'paddle_product_id'],
        'payment_prices' => ['name', 'paddle_price_id', 'currency'],
        'payment_discounts' => ['name', 'code', 'paddle_discount_id'],
        'payment_checkouts' => ['paddle_transaction_id', 'checkout_url'],
        'payment_orders' => ['paddle_transaction_id', 'paddle_customer_id', 'paddle_subscription_id'],
        'payment_subscriptions' => ['paddle_subscription_id', 'paddle_customer_id'],
        'team_accounts' => ['name', 'slug', 'paddle_customer_id', 'paddle_subscription_id'],
        'team_seats' => ['email', 'role'],
        'payment_reconciliation' => ['event_id', 'record_type', 'paddle_transaction_id', 'paddle_subscription_id', 'paddle_customer_id'],
        'gift_purchases' => ['recipient_email', 'recipient_name', 'code'],
        'affiliate_partners' => ['name', 'code', 'payout_email'],
        'affiliate_visits' => ['visitor_id', 'landing_url'],
        'checkout_recoveries' => ['email', 'recovery_token', 'recovery_url'],
        'lead_magnets' => ['title', 'slug', 'description'],
        'newsletter_campaigns' => ['name', 'slug', 'subject'],
        'lead_submissions' => ['email', 'name'],
        'ab_experiments' => ['key', 'name', 'surface'],
        'ab_variants' => ['key', 'name'],
        'social_share_images' => ['title', 'template', 'image_url'],
        'moderation_queue' => ['reason', 'resolution_note'],
        'content_reports' => ['reason', 'details', 'resolution_note'],
        'blocked_words' => ['word', 'notes'],
        'discussion_forums' => ['title', 'slug', 'description'],
        'discussion_threads' => ['title', 'slug', 'body'],
        'community_groups' => ['name', 'slug', 'description'],
        'course_purchases' => ['source'],
        'payment_entitlements' => ['type', 'source', 'entitlementable_type'],
        'payment_order_items' => ['description'],
        'discount_redemptions' => ['code'],
        'payment_webhook_events' => ['provider', 'event_id', 'event_type', 'last_error'],
        'payment_audit_logs' => ['action', 'auditable_type', 'ip_address'],
        'course_questions' => ['title', 'body'],
        'lesson_question_answers' => ['body'],
        'discussion_posts' => ['body'],
        'community_reactions' => ['reactable_type'],
        'reputation_events' => ['reason', 'subject_type'],
        'community_group_members' => ['role'],
        'moderation_history' => ['reason', 'resolution_note', 'subject_type'],
        'database_backups' => ['database_name', 'driver', 'checksum', 'failure_reason', 'verification_failure_reason'],
        'queue_jobs' => ['queue', 'payload'],
        'failed_jobs' => ['uuid', 'connection', 'queue', 'payload', 'exception'],
        'scheduler_heartbeats' => ['name', 'status'],
        'paddle_webhook_health' => ['event_id', 'event_type', 'last_error'],
        'application_logs' => ['message', 'channel', 'environment'],
        'audit_logs' => ['action', 'auditable_type', 'ip_address'],
        'approval_histories' => ['subject_type', 'decision', 'from_status', 'to_status', 'note'],
        'health_checks' => ['check_name', 'status'],
        'operational_alerts' => ['key', 'source', 'severity', 'title', 'message', 'resolution_note'],
    ];

    /** @var array<string, array<string, list<string>>> */
    private const SEARCH_RELATIONS = [
        'bloggers' => ['user' => ['name', 'email']],
        'blog_comments' => ['user' => ['name', 'email'], 'post' => ['title']],
        'creator_agreements' => ['user' => ['name', 'email']],
        'course_enrollments' => ['user' => ['name', 'email'], 'course' => ['title']],
        'drip_schedules' => ['course' => ['title'], 'lesson' => ['title']],
        'quizzes' => ['course' => ['title'], 'lesson' => ['title']],
        'quiz_questions' => ['quiz' => ['title']],
        'quiz_attempts' => ['user' => ['name', 'email'], 'quiz' => ['title'], 'course' => ['title']],
        'assignments' => ['course' => ['title'], 'lesson' => ['title']],
        'assignment_submissions' => ['user' => ['name', 'email'], 'assignment' => ['title'], 'course' => ['title']],
        'certificates' => ['user' => ['name', 'email'], 'course' => ['title']],
        'lesson_progress' => ['user' => ['name', 'email'], 'course' => ['title'], 'lesson' => ['title']],
        'lesson_notes' => ['user' => ['name', 'email'], 'course' => ['title'], 'lesson' => ['title']],
        'lesson_bookmarks' => ['user' => ['name', 'email'], 'course' => ['title'], 'lesson' => ['title']],
        'instructors' => ['user' => ['name', 'email']],
        'revenue_share_rules' => ['course' => ['title'], 'user' => ['name', 'email']],
        'payment_checkouts' => ['user' => ['name', 'email']],
        'payment_orders' => ['user' => ['name', 'email']],
        'payment_subscriptions' => ['user' => ['name', 'email']],
        'team_accounts' => ['owner' => ['name', 'email']],
        'affiliate_visits' => ['partner' => ['code']],
        'referral_conversions' => ['partner' => ['name', 'code'], 'user' => ['email']],
        'ab_variants' => ['experiment' => ['key']],
        'course_purchases' => ['user' => ['name', 'email'], 'course' => ['title'], 'order' => ['paddle_transaction_id']],
        'payment_entitlements' => ['user' => ['name', 'email'], 'teamAccount' => ['name'], 'order' => ['paddle_transaction_id'], 'subscription' => ['paddle_subscription_id']],
        'payment_order_items' => ['order' => ['paddle_transaction_id'], 'product' => ['name'], 'course' => ['title']],
        'discount_redemptions' => ['discount' => ['name', 'code'], 'user' => ['name', 'email']],
        'payment_audit_logs' => ['actor' => ['name', 'email'], 'user' => ['name', 'email']],
        'course_questions' => ['user' => ['name', 'email'], 'course' => ['title'], 'lesson' => ['title']],
        'lesson_question_answers' => ['user' => ['name', 'email'], 'question' => ['title']],
        'discussion_posts' => ['user' => ['name', 'email'], 'thread' => ['title']],
        'community_reactions' => ['user' => ['name', 'email']],
        'reputation_events' => ['user' => ['name', 'email'], 'actor' => ['name', 'email']],
        'community_reputation_scores' => ['user' => ['name', 'email']],
        'community_group_members' => ['user' => ['name', 'email'], 'group' => ['name']],
        'moderation_history' => ['reporter' => ['name', 'email'], 'assignee' => ['name', 'email']],
        'audit_logs' => ['actor' => ['name', 'email']],
        'approval_histories' => ['actor' => ['name', 'email']],
        'operational_alerts' => ['acknowledgedBy' => ['name', 'email'], 'resolvedBy' => ['name', 'email']],
    ];

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function apply(Builder $query, string $resource, Request $request): Builder
    {
        $search = $request->query('search');
        $status = $request->query('status');
        $category = $request->query('category');

        if (is_string($search) && filled($search)) {
            $this->applySearch($query, $resource, $search);
        }

        if (is_string($status) && filled($status)) {
            if ($resource === 'application_logs') {
                $query->where('level', $status);
            } else {
                $this->applyStatus($query, $resource, $status);
            }
        }

        if (is_string($category) && filled($category)) {
            $this->applyCategory($query, $resource, $category);
        }

        $role = $request->query('role');
        if ($resource === 'users' && is_string($role) && filled($role)) {
            $query->whereHas('roles', fn (Builder $query) => $query->where('name', $role));
        }

        $group = $request->query('group');
        if ($resource === 'settings' && is_string($group) && filled($group)) {
            $query->where('group', $group);
        }

        return $query;
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applySearch(Builder $query, string $resource, string $search): void
    {
        $columns = self::SEARCH_COLUMNS[$resource] ?? [];
        $relations = self::SEARCH_RELATIONS[$resource] ?? [];

        if ($columns === [] && $relations === []) {
            return;
        }

        $query->where(function (Builder $query) use ($columns, $relations, $search): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $query->{$method}($column, 'like', "%{$search}%");
            }

            foreach ($relations as $relation => $relationColumns) {
                $query->orWhereHas($relation, function (Builder $query) use ($relationColumns, $search): void {
                    foreach ($relationColumns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $query->{$method}($column, 'like', "%{$search}%");
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyStatus(Builder $query, string $resource, string $status): void
    {
        if (in_array($resource, self::STATUS_RESOURCES, true)) {
            $query->where('status', $status);

            return;
        }

        if ($resource === 'reviewer_comments') {
            $query->where('is_resolved', $status === 'resolved');

            return;
        }

        if ($resource === 'media') {
            $query->where('visibility', $status);

            return;
        }

        if (in_array($resource, self::ACTIVE_RESOURCES, true)) {
            $query->where('is_active', $status === 'active');
        }
    }

    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    private function applyCategory(Builder $query, string $resource, string $category): void
    {
        if (in_array($resource, ['learning_paths', 'course_bundles', 'skill_tracks'], true)) {
            $query->whereHas('courses', fn (Builder $query) => $query->where('courses.id', $category));

            return;
        }

        if ($resource === 'payment_orders') {
            $query->whereHas('items', fn (Builder $query) => $query->where('payment_product_id', $category));

            return;
        }

        $column = self::CATEGORY_COLUMNS[$resource] ?? null;

        if ($column) {
            $query->where($column, $category);
        }
    }
}
