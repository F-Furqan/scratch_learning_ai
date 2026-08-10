<?php

return [
    'domains' => [
        'catalog' => ['label' => 'Catalog', 'icon' => 'Library', 'order' => 10],
        'editorial' => ['label' => 'Editorial', 'icon' => 'Newspaper', 'order' => 20],
        'learning' => ['label' => 'Learning', 'icon' => 'GraduationCap', 'order' => 30],
        'commerce' => ['label' => 'Commerce', 'icon' => 'BadgeDollarSign', 'order' => 40],
        'community' => ['label' => 'Community', 'icon' => 'MessagesSquare', 'order' => 50],
        'growth' => ['label' => 'Growth', 'icon' => 'TrendingUp', 'order' => 60],
        'operations' => ['label' => 'Operations', 'icon' => 'Settings', 'order' => 70],
    ],

    'resources' => [
        'course_categories' => [
            'domain' => 'catalog', 'title' => 'Course Categories', 'singular' => 'Course category',
            'path' => 'course-categories', 'route' => 'course-categories', 'permissions' => ['manage_courses'], 'order' => 10,
        ],
        'course_subcategories' => [
            'domain' => 'catalog', 'title' => 'Course Subcategories', 'singular' => 'Course subcategory',
            'path' => 'course-subcategories', 'route' => 'course-subcategories', 'permissions' => ['manage_courses'], 'order' => 20,
        ],
        'courses' => [
            'domain' => 'catalog', 'title' => 'Courses', 'singular' => 'Course',
            'path' => 'courses', 'route' => 'courses', 'permissions' => ['manage_courses'], 'order' => 30,
        ],
        'course_sections' => [
            'domain' => 'catalog', 'title' => 'Course Sections', 'singular' => 'Course section',
            'path' => 'course-sections', 'route' => 'course-sections', 'permissions' => ['manage_courses'], 'order' => 40,
        ],
        'course_resources' => [
            'domain' => 'catalog', 'title' => 'Course Resources', 'singular' => 'Course resource',
            'path' => 'course-resources', 'route' => 'course-resources', 'permissions' => ['manage_courses'], 'order' => 50,
        ],
        'course_faqs' => [
            'domain' => 'catalog', 'title' => 'Course FAQs', 'singular' => 'Course FAQ',
            'path' => 'course-faqs', 'route' => 'course-faqs', 'permissions' => ['manage_courses'], 'order' => 60,
        ],
        'trash' => [
            'domain' => 'catalog', 'title' => 'Trash', 'singular' => 'Trash item',
            'path' => 'trash', 'route' => 'trash', 'permissions' => ['manage_courses', 'manage_blogs'], 'order' => 90,
        ],

        'bloggers' => [
            'domain' => 'editorial', 'title' => 'Blogger Approvals', 'singular' => 'Blogger profile',
            'path' => 'bloggers', 'route' => 'bloggers', 'permissions' => ['approve_bloggers'], 'order' => 10,
        ],
        'blogs' => [
            'domain' => 'editorial', 'title' => 'Blogs', 'singular' => 'Blog post',
            'path' => 'blogs', 'route' => 'blogs', 'permissions' => ['manage_blogs'], 'order' => 20,
        ],
        'blog_categories' => [
            'domain' => 'editorial', 'title' => 'Blog Categories', 'singular' => 'Blog category',
            'path' => 'blog-categories', 'route' => 'blog-categories', 'permissions' => ['manage_blogs'], 'order' => 30,
        ],
        'blog_tags' => [
            'domain' => 'editorial', 'title' => 'Blog Tags', 'singular' => 'Blog tag',
            'path' => 'blog-tags', 'route' => 'blog-tags', 'permissions' => ['manage_blogs'], 'order' => 40,
        ],
        'blog_faqs' => [
            'domain' => 'editorial', 'title' => 'Blog FAQs', 'singular' => 'Blog FAQ',
            'path' => 'blog-faqs', 'route' => 'blog-faqs', 'permissions' => ['manage_blogs'], 'order' => 50,
        ],
        'blog_comments' => [
            'domain' => 'editorial', 'title' => 'Blog Comments', 'singular' => 'Blog comment',
            'path' => 'blog-comments', 'route' => 'blog-comments', 'permissions' => ['manage_blogs'], 'order' => 60,
        ],
        'cms' => [
            'domain' => 'editorial', 'title' => 'CMS Pages', 'singular' => 'Page',
            'path' => 'cms', 'route' => 'cms', 'permissions' => ['manage_cms'], 'order' => 70,
        ],
        'content_blocks' => [
            'domain' => 'editorial', 'title' => 'CMS Content Blocks', 'singular' => 'Content block',
            'path' => 'cms/content-blocks', 'route' => 'cms.content-blocks', 'permissions' => ['manage_cms'], 'order' => 80,
        ],
        'home_hero' => [
            'domain' => 'editorial', 'title' => 'Home Hero', 'singular' => 'Home hero setting',
            'path' => 'cms/home-hero', 'route' => 'cms.home-hero', 'permissions' => ['manage_cms', 'manage_settings'], 'order' => 90,
        ],
        'home_hero_slides' => [
            'domain' => 'editorial', 'title' => 'Home Hero Slides', 'singular' => 'Home hero slide',
            'path' => 'cms/home-hero-slides', 'route' => 'cms.home-hero-slides', 'permissions' => ['manage_cms', 'manage_settings', 'manage_media'], 'order' => 100,
        ],
        'home_page_sections' => [
            'domain' => 'editorial', 'title' => 'Homepage Sections', 'singular' => 'Homepage section',
            'path' => 'cms/homepage-sections', 'route' => 'cms.homepage-sections', 'permissions' => ['manage_cms', 'manage_settings'], 'order' => 110,
        ],
        'menus' => [
            'domain' => 'editorial', 'title' => 'Menus', 'singular' => 'Menu',
            'path' => 'menus', 'route' => 'menus', 'permissions' => ['manage_cms'], 'order' => 120,
        ],
        'menu_items' => [
            'domain' => 'editorial', 'title' => 'Menu Items', 'singular' => 'Menu item',
            'path' => 'menus/items', 'route' => 'menus.items', 'permissions' => ['manage_cms'], 'order' => 130,
        ],
        'media' => [
            'domain' => 'editorial', 'title' => 'Media Library', 'singular' => 'Media asset',
            'path' => 'media', 'route' => 'media', 'permissions' => ['manage_media'], 'order' => 140,
        ],
        'media_folders' => [
            'domain' => 'editorial', 'title' => 'Media Folders', 'singular' => 'Media folder',
            'path' => 'media/folders', 'route' => 'media.folders', 'permissions' => ['manage_media'], 'order' => 150,
        ],
        'media_usages' => [
            'domain' => 'editorial', 'title' => 'Media Usage', 'singular' => 'Media usage reference',
            'path' => 'media/usages', 'route' => 'media.usages', 'permissions' => ['manage_media'], 'order' => 160,
        ],
        'creator_agreements' => [
            'domain' => 'editorial', 'title' => 'Creator Agreements', 'singular' => 'Creator agreement acceptance',
            'path' => 'creators/agreements', 'route' => 'creators.agreements', 'permissions' => ['approve_bloggers'], 'order' => 170,
        ],
        'instructors' => [
            'domain' => 'editorial', 'title' => 'Instructor Profiles', 'singular' => 'Instructor profile',
            'path' => 'creators/instructors', 'route' => 'creators.instructors', 'permissions' => ['manage_courses', 'manage_blogs', 'approve_bloggers'], 'order' => 180,
        ],
        'author_badges' => [
            'domain' => 'editorial', 'title' => 'Author Badges', 'singular' => 'Author badge',
            'path' => 'creators/badges', 'route' => 'creators.badges', 'permissions' => ['manage_courses', 'manage_blogs', 'approve_bloggers'], 'order' => 190,
        ],
        'editorial_revisions' => [
            'domain' => 'editorial', 'title' => 'Editorial Revisions', 'singular' => 'Editorial revision',
            'path' => 'editorial/revisions', 'route' => 'editorial.revisions', 'permissions' => ['manage_blogs', 'manage_courses', 'manage_cms'], 'order' => 200,
        ],
        'reviewer_comments' => [
            'domain' => 'editorial', 'title' => 'Reviewer Comments', 'singular' => 'Reviewer comment',
            'path' => 'editorial/comments', 'route' => 'editorial.comments', 'permissions' => ['manage_blogs', 'manage_courses', 'manage_cms'], 'order' => 210,
        ],
        'scheduled_publications' => [
            'domain' => 'editorial', 'title' => 'Scheduled Publishing', 'singular' => 'Scheduled publication',
            'path' => 'editorial/scheduled', 'route' => 'editorial.scheduled', 'permissions' => ['manage_blogs', 'manage_courses', 'manage_cms'], 'order' => 220,
        ],

        'lessons' => [
            'domain' => 'learning', 'title' => 'Lessons', 'singular' => 'Lesson',
            'path' => 'lessons', 'route' => 'lessons', 'permissions' => ['manage_lessons', 'manage_courses'], 'order' => 10,
        ],
        'course_enrollments' => [
            'domain' => 'learning', 'title' => 'Enrollments', 'singular' => 'Enrollment',
            'path' => 'learning/enrollments', 'route' => 'learning.enrollments', 'permissions' => ['manage_courses'], 'order' => 20,
        ],
        'learning_paths' => [
            'domain' => 'learning', 'title' => 'Learning Paths', 'singular' => 'Learning path',
            'path' => 'learning/paths', 'route' => 'learning.paths', 'permissions' => ['manage_courses'], 'order' => 30,
        ],
        'course_bundles' => [
            'domain' => 'learning', 'title' => 'Course Bundles', 'singular' => 'Course bundle',
            'path' => 'learning/bundles', 'route' => 'learning.bundles', 'permissions' => ['manage_courses', 'manage_payments'], 'order' => 40,
        ],
        'skill_tracks' => [
            'domain' => 'learning', 'title' => 'Skill Tracks', 'singular' => 'Skill track',
            'path' => 'learning/skill-tracks', 'route' => 'learning.skill-tracks', 'permissions' => ['manage_courses'], 'order' => 50,
        ],
        'drip_schedules' => [
            'domain' => 'learning', 'title' => 'Drip Schedules', 'singular' => 'Drip schedule',
            'path' => 'learning/drip-schedules', 'route' => 'learning.drip-schedules', 'permissions' => ['manage_courses', 'manage_lessons'], 'order' => 60,
        ],
        'quizzes' => [
            'domain' => 'learning', 'title' => 'Quizzes', 'singular' => 'Quiz',
            'path' => 'learning/quizzes', 'route' => 'learning.quizzes', 'permissions' => ['manage_courses', 'manage_lessons'], 'order' => 70,
        ],
        'quiz_questions' => [
            'domain' => 'learning', 'title' => 'Quiz Questions', 'singular' => 'Quiz question',
            'path' => 'learning/quiz-questions', 'route' => 'learning.quiz-questions', 'permissions' => ['manage_courses', 'manage_lessons'], 'order' => 80,
        ],
        'quiz_attempts' => [
            'domain' => 'learning', 'title' => 'Quiz Attempts', 'singular' => 'Quiz attempt',
            'path' => 'learning/quiz-attempts', 'route' => 'learning.quiz-attempts', 'permissions' => ['manage_courses'], 'order' => 90,
        ],
        'assignments' => [
            'domain' => 'learning', 'title' => 'Assignments', 'singular' => 'Assignment',
            'path' => 'learning/assignments', 'route' => 'learning.assignments', 'permissions' => ['manage_courses', 'manage_lessons'], 'order' => 100,
        ],
        'assignment_submissions' => [
            'domain' => 'learning', 'title' => 'Assignment Submissions', 'singular' => 'Assignment submission',
            'path' => 'learning/assignment-submissions', 'route' => 'learning.assignment-submissions', 'permissions' => ['manage_courses'], 'order' => 110,
        ],
        'certificates' => [
            'domain' => 'learning', 'title' => 'Certificates', 'singular' => 'Certificate',
            'path' => 'learning/certificates', 'route' => 'learning.certificates', 'permissions' => ['manage_courses'], 'order' => 120,
        ],
        'lesson_progress' => [
            'domain' => 'learning', 'title' => 'Student Progress', 'singular' => 'Progress record',
            'path' => 'learning/progress', 'route' => 'learning.progress', 'permissions' => ['manage_courses'], 'order' => 130,
        ],
        'lesson_notes' => [
            'domain' => 'learning', 'title' => 'Student Notes', 'singular' => 'Student note',
            'path' => 'learning/notes', 'route' => 'learning.notes', 'permissions' => ['manage_courses'], 'order' => 140,
        ],
        'lesson_bookmarks' => [
            'domain' => 'learning', 'title' => 'Student Bookmarks', 'singular' => 'Student bookmark',
            'path' => 'learning/bookmarks', 'route' => 'learning.bookmarks', 'permissions' => ['manage_courses'], 'order' => 150,
        ],

        'revenue_share_rules' => [
            'domain' => 'commerce', 'title' => 'Revenue Share Rules', 'singular' => 'Revenue share rule',
            'path' => 'payments/revenue-share', 'route' => 'payments.revenue-share', 'permissions' => ['manage_payments'], 'order' => 10,
        ],
        'payment_products' => [
            'domain' => 'commerce', 'title' => 'Payment Products', 'singular' => 'Payment product',
            'path' => 'payments/products', 'route' => 'payments.products', 'permissions' => ['manage_payments'], 'order' => 20,
        ],
        'payment_prices' => [
            'domain' => 'commerce', 'title' => 'Payment Plans', 'singular' => 'Payment plan',
            'path' => 'payments/prices', 'route' => 'payments.prices', 'permissions' => ['manage_payments'], 'order' => 30,
        ],
        'payment_checkouts' => [
            'domain' => 'commerce', 'title' => 'Checkout Sessions', 'singular' => 'Checkout session',
            'path' => 'payments/checkouts', 'route' => 'payments.checkouts', 'permissions' => ['manage_payments'], 'order' => 40,
        ],
        'payment_orders' => [
            'domain' => 'commerce', 'title' => 'Payment Orders', 'singular' => 'Payment order',
            'path' => 'payments/orders', 'route' => 'payments.orders', 'permissions' => ['manage_payments'], 'order' => 50,
        ],
        'payment_subscriptions' => [
            'domain' => 'commerce', 'title' => 'Subscriptions', 'singular' => 'Subscription',
            'path' => 'payments/subscriptions', 'route' => 'payments.subscriptions', 'permissions' => ['manage_payments'], 'order' => 60,
        ],
        'team_accounts' => [
            'domain' => 'commerce', 'title' => 'Team Accounts', 'singular' => 'Team account',
            'path' => 'payments/teams', 'route' => 'payments.teams', 'permissions' => ['manage_payments'], 'order' => 70,
        ],
        'team_seats' => [
            'domain' => 'commerce', 'title' => 'Team Seats', 'singular' => 'Team seat',
            'path' => 'payments/seats', 'route' => 'payments.seats', 'permissions' => ['manage_payments'], 'order' => 80,
        ],
        'payment_reconciliation' => [
            'domain' => 'commerce', 'title' => 'Payment Reconciliation', 'singular' => 'Reconciliation record',
            'path' => 'payments/reconciliation', 'route' => 'payments.reconciliation', 'permissions' => ['admin.payments.reconcile'], 'order' => 150,
        ],
        'course_purchases' => [
            'domain' => 'commerce', 'title' => 'Course Purchases', 'singular' => 'Course purchase',
            'path' => 'payments/course-purchases', 'route' => 'payments.course-purchases', 'permissions' => ['manage_payments'], 'order' => 90,
        ],
        'payment_entitlements' => [
            'domain' => 'commerce', 'title' => 'Entitlements', 'singular' => 'Payment entitlement',
            'path' => 'payments/entitlements', 'route' => 'payments.entitlements', 'permissions' => ['manage_payments'], 'order' => 100,
        ],
        'payment_order_items' => [
            'domain' => 'commerce', 'title' => 'Order Items', 'singular' => 'Order item',
            'path' => 'payments/order-items', 'route' => 'payments.order-items', 'permissions' => ['manage_payments'], 'order' => 110,
        ],
        'discount_redemptions' => [
            'domain' => 'commerce', 'title' => 'Discount Redemptions', 'singular' => 'Discount redemption',
            'path' => 'payments/discount-redemptions', 'route' => 'payments.discount-redemptions', 'permissions' => ['manage_payments'], 'order' => 120,
        ],
        'payment_webhook_events' => [
            'domain' => 'commerce', 'title' => 'Webhook Events', 'singular' => 'Webhook event',
            'path' => 'payments/webhook-events', 'route' => 'payments.webhook-events', 'permissions' => ['manage_payments'], 'order' => 130,
        ],
        'payment_audit_logs' => [
            'domain' => 'commerce', 'title' => 'Payment Audit Logs', 'singular' => 'Payment audit log',
            'path' => 'payments/audit-logs', 'route' => 'payments.audit-logs', 'permissions' => ['manage_payments'], 'order' => 140,
        ],

        'moderation_queue' => [
            'domain' => 'community', 'title' => 'Moderation Queue', 'singular' => 'Moderation item',
            'path' => 'community/moderation', 'route' => 'community.moderation', 'permissions' => ['manage_courses', 'manage_blogs', 'manage_cms'], 'order' => 10,
        ],
        'content_reports' => [
            'domain' => 'community', 'title' => 'Content Reports', 'singular' => 'Content report',
            'path' => 'community/reports', 'route' => 'community.reports', 'permissions' => ['manage_courses', 'manage_blogs', 'manage_cms'], 'order' => 20,
        ],
        'blocked_words' => [
            'domain' => 'community', 'title' => 'Blocked Words', 'singular' => 'Blocked word',
            'path' => 'community/blocked-words', 'route' => 'community.blocked-words', 'permissions' => ['manage_courses', 'manage_blogs', 'manage_cms'], 'order' => 30,
        ],
        'discussion_forums' => [
            'domain' => 'community', 'title' => 'Discussion Forums', 'singular' => 'Discussion forum',
            'path' => 'community/forums', 'route' => 'community.forums', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 40,
        ],
        'discussion_threads' => [
            'domain' => 'community', 'title' => 'Discussion Threads', 'singular' => 'Discussion thread',
            'path' => 'community/threads', 'route' => 'community.threads', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 50,
        ],
        'community_groups' => [
            'domain' => 'community', 'title' => 'Community Groups', 'singular' => 'Community group',
            'path' => 'community/groups', 'route' => 'community.groups', 'permissions' => ['manage_courses', 'manage_payments'], 'order' => 60,
        ],
        'course_questions' => [
            'domain' => 'community', 'title' => 'Course Q&A', 'singular' => 'Course question',
            'path' => 'community/questions', 'route' => 'community.questions', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 70,
        ],
        'lesson_question_answers' => [
            'domain' => 'community', 'title' => 'Q&A Answers', 'singular' => 'Question answer',
            'path' => 'community/answers', 'route' => 'community.answers', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 80,
        ],
        'discussion_posts' => [
            'domain' => 'community', 'title' => 'Discussion Posts', 'singular' => 'Discussion post',
            'path' => 'community/posts', 'route' => 'community.posts', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 90,
        ],
        'community_reactions' => [
            'domain' => 'community', 'title' => 'Reactions', 'singular' => 'Community reaction',
            'path' => 'community/reactions', 'route' => 'community.reactions', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 100,
        ],
        'reputation_events' => [
            'domain' => 'community', 'title' => 'Reputation Events', 'singular' => 'Reputation event',
            'path' => 'community/reputation-events', 'route' => 'community.reputation-events', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 110,
        ],
        'community_reputation_scores' => [
            'domain' => 'community', 'title' => 'Reputation Scores', 'singular' => 'Reputation score',
            'path' => 'community/reputation-scores', 'route' => 'community.reputation-scores', 'permissions' => ['manage_courses', 'manage_cms'], 'order' => 120,
        ],
        'community_group_members' => [
            'domain' => 'community', 'title' => 'Group Members', 'singular' => 'Group member',
            'path' => 'community/group-members', 'route' => 'community.group-members', 'permissions' => ['manage_courses', 'manage_payments'], 'order' => 130,
        ],
        'moderation_history' => [
            'domain' => 'community', 'title' => 'Moderation History', 'singular' => 'Moderation history record',
            'path' => 'community/moderation-history', 'route' => 'community.moderation-history', 'permissions' => ['manage_courses', 'manage_blogs', 'manage_cms'], 'order' => 140,
        ],

        'ad_zones' => [
            'domain' => 'growth', 'title' => 'Ad Zones', 'singular' => 'Ad zone',
            'path' => 'ads/zones', 'route' => 'ads.zones', 'permissions' => ['manage_ads'], 'order' => 10,
        ],
        'ad_campaigns' => [
            'domain' => 'growth', 'title' => 'Ad Campaigns', 'singular' => 'Ad campaign',
            'path' => 'ads/campaigns', 'route' => 'ads.campaigns', 'permissions' => ['manage_ads'], 'order' => 20,
        ],
        'ad_creatives' => [
            'domain' => 'growth', 'title' => 'Ad Creatives', 'singular' => 'Ad creative',
            'path' => 'ads/creatives', 'route' => 'ads.creatives', 'permissions' => ['manage_ads'], 'order' => 30,
        ],
        'advertiser_requests' => [
            'domain' => 'growth', 'title' => 'Advertiser Requests', 'singular' => 'Advertiser request',
            'path' => 'ads/advertiser-requests', 'route' => 'ads.advertiser-requests', 'permissions' => ['manage_ads'], 'order' => 40,
        ],
        'ad_pricing' => [
            'domain' => 'growth', 'title' => 'Ad Pricing', 'singular' => 'Ad pricing setting',
            'path' => 'ads/pricing', 'route' => 'ads.pricing', 'permissions' => ['manage_ads'], 'order' => 50,
        ],
        'payment_discounts' => [
            'domain' => 'growth', 'title' => 'Discounts & Coupons', 'singular' => 'Payment discount',
            'path' => 'growth/discounts', 'route' => 'growth.discounts', 'permissions' => ['manage_payments'], 'order' => 60,
        ],
        'gift_purchases' => [
            'domain' => 'growth', 'title' => 'Gift Purchases', 'singular' => 'Gift purchase',
            'path' => 'growth/gifts', 'route' => 'growth.gifts', 'permissions' => ['manage_payments'], 'order' => 70,
        ],
        'affiliate_partners' => [
            'domain' => 'growth', 'title' => 'Affiliate Partners', 'singular' => 'Affiliate partner',
            'path' => 'growth/affiliates', 'route' => 'growth.affiliates', 'permissions' => ['manage_payments'], 'order' => 80,
        ],
        'affiliate_visits' => [
            'domain' => 'growth', 'title' => 'Affiliate Visits', 'singular' => 'Affiliate visit',
            'path' => 'growth/affiliate-visits', 'route' => 'growth.affiliate-visits', 'permissions' => ['manage_payments'], 'order' => 90,
        ],
        'referral_conversions' => [
            'domain' => 'growth', 'title' => 'Referral Conversions', 'singular' => 'Referral conversion',
            'path' => 'growth/referrals', 'route' => 'growth.referrals', 'permissions' => ['manage_payments'], 'order' => 100,
        ],
        'checkout_recoveries' => [
            'domain' => 'growth', 'title' => 'Checkout Recoveries', 'singular' => 'Checkout recovery',
            'path' => 'growth/checkout-recoveries', 'route' => 'growth.checkout-recoveries', 'permissions' => ['manage_payments'], 'order' => 110,
        ],
        'lead_magnets' => [
            'domain' => 'growth', 'title' => 'Lead Magnets', 'singular' => 'Lead magnet',
            'path' => 'growth/lead-magnets', 'route' => 'growth.lead-magnets', 'permissions' => ['manage_payments', 'manage_cms'], 'order' => 120,
        ],
        'newsletter_campaigns' => [
            'domain' => 'growth', 'title' => 'Newsletter Campaigns', 'singular' => 'Newsletter campaign',
            'path' => 'growth/newsletters', 'route' => 'growth.newsletters', 'permissions' => ['manage_payments', 'manage_cms'], 'order' => 130,
        ],
        'lead_submissions' => [
            'domain' => 'growth', 'title' => 'Lead Submissions', 'singular' => 'Lead submission',
            'path' => 'growth/leads', 'route' => 'growth.leads', 'permissions' => ['manage_payments', 'manage_cms'], 'order' => 140,
        ],
        'ab_experiments' => [
            'domain' => 'growth', 'title' => 'A/B Experiments', 'singular' => 'A/B experiment',
            'path' => 'growth/experiments', 'route' => 'growth.experiments', 'permissions' => ['manage_payments', 'manage_cms'], 'order' => 150,
        ],
        'ab_variants' => [
            'domain' => 'growth', 'title' => 'A/B Variants', 'singular' => 'A/B variant',
            'path' => 'growth/variants', 'route' => 'growth.variants', 'permissions' => ['manage_payments', 'manage_cms'], 'order' => 160,
        ],
        'social_share_images' => [
            'domain' => 'growth', 'title' => 'Social Share Images', 'singular' => 'Social share image',
            'path' => 'growth/social-images', 'route' => 'growth.social-images', 'permissions' => ['manage_payments', 'manage_cms', 'manage_media'], 'order' => 170,
        ],

        'users' => [
            'domain' => 'operations', 'title' => 'Users', 'singular' => 'User',
            'path' => 'users', 'route' => 'users', 'permissions' => ['manage_users'], 'order' => 10,
        ],
        'roles' => [
            'domain' => 'operations', 'title' => 'Roles', 'singular' => 'Role',
            'path' => 'roles', 'route' => 'roles', 'permissions' => ['manage_roles', 'manage_permissions'], 'order' => 20,
        ],
        'permissions' => [
            'domain' => 'operations', 'title' => 'Permissions', 'singular' => 'Permission',
            'path' => 'permissions', 'route' => 'permissions', 'permissions' => ['manage_permissions'], 'order' => 30,
        ],
        'settings' => [
            'domain' => 'operations', 'title' => 'Settings', 'singular' => 'Setting',
            'path' => 'settings', 'route' => 'settings', 'permissions' => ['manage_settings'], 'order' => 40,
        ],
        'database_backups' => [
            'domain' => 'operations', 'title' => 'Backup History', 'singular' => 'Database backup',
            'path' => 'operations/backups', 'route' => 'operations.backups', 'permissions' => ['manage_settings'], 'order' => 50,
        ],
        'queue_jobs' => [
            'domain' => 'operations', 'title' => 'Queue Health', 'singular' => 'Queued job',
            'path' => 'operations/queue', 'route' => 'operations.queue', 'permissions' => ['manage_settings'], 'order' => 60,
        ],
        'failed_jobs' => [
            'domain' => 'operations', 'title' => 'Failed Jobs', 'singular' => 'Failed job',
            'path' => 'operations/failed-jobs', 'route' => 'operations.failed-jobs', 'permissions' => ['manage_settings'], 'order' => 70,
        ],
        'scheduler_heartbeats' => [
            'domain' => 'operations', 'title' => 'Scheduler Heartbeat', 'singular' => 'Scheduler heartbeat',
            'path' => 'operations/scheduler', 'route' => 'operations.scheduler', 'permissions' => ['manage_settings'], 'order' => 80,
        ],
        'paddle_webhook_health' => [
            'domain' => 'operations', 'title' => 'Paddle Webhook Health', 'singular' => 'Paddle webhook event',
            'path' => 'operations/paddle-webhooks', 'route' => 'operations.paddle-webhooks', 'permissions' => ['manage_settings', 'manage_payments'], 'order' => 90,
        ],
        'application_logs' => [
            'domain' => 'operations', 'title' => 'Application Logs', 'singular' => 'Application log entry',
            'path' => 'operations/logs', 'route' => 'operations.logs', 'permissions' => ['manage_settings'], 'order' => 100,
        ],
        'audit_logs' => [
            'domain' => 'operations', 'title' => 'Audit Logs', 'singular' => 'Audit log',
            'path' => 'operations/audit-logs', 'route' => 'operations.audit-logs', 'permissions' => ['manage_settings'], 'order' => 110,
        ],
        'approval_histories' => [
            'domain' => 'operations', 'title' => 'Approval Histories', 'singular' => 'Approval history',
            'path' => 'operations/approval-histories', 'route' => 'operations.approval-histories', 'permissions' => ['manage_settings', 'manage_courses', 'manage_blogs'], 'order' => 120,
        ],
        'health_checks' => [
            'domain' => 'operations', 'title' => 'Health Checks', 'singular' => 'Health check run',
            'path' => 'operations/health', 'route' => 'operations.health', 'permissions' => ['manage_settings'], 'order' => 130,
        ],
        'operational_alerts' => [
            'domain' => 'operations', 'title' => 'Operational Alerts', 'singular' => 'Operational alert',
            'path' => 'operations/alerts', 'route' => 'operations.alerts', 'permissions' => ['manage_settings'], 'order' => 140,
        ],
    ],
];
