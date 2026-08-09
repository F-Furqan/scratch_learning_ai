<?php

namespace Database\Seeders;

use App\Enums\BloggerStatus;
use App\Enums\HomePageSectionType;
use App\Enums\InstructorProfileStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\VideoType;
use App\Models\AuthorBadge;
use App\Models\BlogCategory;
use App\Models\BlogFaq;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\ContentBlock;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\CourseSubcategory;
use App\Models\CreatorAgreementAcceptance;
use App\Models\HomeHeroSlide;
use App\Models\HomePageSection;
use App\Models\InstructorProfile;
use App\Models\MediaAsset;
use App\Models\MediaFolder;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ContentSeeder extends Seeder
{
    /**
     * Seed visual demo content for courses, blogs, creators, CMS, FAQs, menus, and media.
     */
    public function run(): void
    {
        $authors = $this->seedAuthors();

        $this->seedBrandMedia($authors['content']);

        [$courseCategories, $courseSubcategories] = $this->seedCourseTaxonomy();
        $this->seedCourses($authors, $courseCategories, $courseSubcategories);

        [$blogCategories, $blogTags] = $this->seedBlogTaxonomy();
        $this->seedBlogPosts($authors, $blogCategories, $blogTags);

        $this->seedPages($authors['content']);
        $this->seedMenus();
        $this->seedSiteSettings();
        $this->seedHomeHeroSlides();
        $this->seedHomePageSections();
    }

    /**
     * @return array<string, User>
     */
    private function seedAuthors(): array
    {
        $definitions = [
            'content' => [
                'name' => 'Content Manager',
                'email' => 'content.manager@example.com',
                'slug' => 'content-manager',
                'headline' => 'Industrial Laravel instructor',
                'bio' => 'Builds practical learning paths for production-grade Laravel teams, editorial teams, and platform owners.',
                'credentials' => 'Platform engineering, Laravel architecture, editorial training operations.',
                'expertise' => 'Laravel Platforms',
                'verified' => true,
                'badge' => 'verified-expert',
                'color' => 'emerald',
            ],
            'maya' => [
                'name' => 'Maya Operations',
                'email' => 'maya.ops@example.com',
                'slug' => 'maya-operations',
                'headline' => 'Learning operations strategist',
                'bio' => 'Designs student journeys, progress systems, content calendars, and support workflows for high-retention academies.',
                'credentials' => 'Learning operations, customer education, cohort enablement.',
                'expertise' => 'Student Success',
                'verified' => true,
                'badge' => 'learning-operator',
                'color' => 'sky',
            ],
            'noah' => [
                'name' => 'Noah Analytics',
                'email' => 'noah.analytics@example.com',
                'slug' => 'noah-analytics',
                'headline' => 'Analytics and reporting architect',
                'bio' => 'Turns product events, revenue signals, and learning outcomes into dashboards that leaders can actually use.',
                'credentials' => 'BI modeling, cohort analysis, learning analytics.',
                'expertise' => 'Analytics & Reporting',
                'verified' => true,
                'badge' => 'data-practitioner',
                'color' => 'violet',
            ],
            'zara' => [
                'name' => 'Zara Growth',
                'email' => 'zara.growth@example.com',
                'slug' => 'zara-growth',
                'headline' => 'Growth and monetization lead',
                'bio' => 'Helps learning platforms package offers, subscriptions, referrals, and launch campaigns without damaging trust.',
                'credentials' => 'Pricing strategy, paid acquisition, lifecycle marketing.',
                'expertise' => 'Growth & Monetization',
                'verified' => false,
                'badge' => 'growth-builder',
                'color' => 'amber',
            ],
        ];

        $authors = [];

        foreach ($definitions as $key => $definition) {
            $user = User::query()->updateOrCreate(
                ['email' => $definition['email']],
                [
                    'name' => $definition['name'],
                    'password' => Hash::make('password'),
                ],
            );

            $user->assignRole(RoleName::SubAdmin->value);
            $user->assignRole(RoleName::Blogger->value);

            BloggerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'profile_photo_path' => null,
                    'bio' => $definition['bio'],
                    'expertise' => $definition['expertise'],
                    'linkedin_url' => 'https://www.linkedin.com/company/scratch-learning',
                    'website_url' => config('app.url'),
                    'application_reason' => 'Seeded expert profile for public demo content.',
                    'status' => BloggerStatus::Approved,
                    'reviewed_by' => $user->id,
                    'reviewed_at' => now()->subDays(8),
                    'admin_notes' => 'Approved automatically by demo content seeder.',
                ],
            );

            InstructorProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'reviewed_by' => $user->id,
                    'display_name' => $definition['name'],
                    'slug' => $definition['slug'],
                    'headline' => $definition['headline'],
                    'bio' => $definition['bio'],
                    'credentials' => $definition['credentials'],
                    'expertise' => $definition['expertise'],
                    'website_url' => config('app.url'),
                    'linkedin_url' => 'https://www.linkedin.com/company/scratch-learning',
                    'status' => InstructorProfileStatus::Approved,
                    'is_verified_expert' => $definition['verified'],
                    'accepts_revenue_share' => true,
                    'payout_currency' => 'USD',
                    'reviewed_at' => now()->subDays(8),
                    'admin_notes' => 'Approved seeded instructor profile.',
                    'metadata' => ['seeded' => true],
                ],
            );

            CreatorAgreementAcceptance::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'terms_version' => config('platform.creator_agreement.version'),
                ],
                [
                    'accepted_at' => now()->subDays(9),
                    'ip_address' => '127.0.0.1',
                    'user_agent' => 'Scratch Learning content seeder',
                ],
            );

            $authors[$key] = $user;
        }

        foreach ($definitions as $key => $definition) {
            $badge = AuthorBadge::query()->updateOrCreate(
                ['slug' => $definition['badge']],
                [
                    'name' => str($definition['badge'])->replace('-', ' ')->title(),
                    'description' => "{$definition['name']} has verified practical experience in {$definition['expertise']}.",
                    'icon' => $definition['verified'] ? 'BadgeCheck' : 'Sparkles',
                    'color' => $definition['color'],
                    'marks_verified_expert' => $definition['verified'],
                    'is_active' => true,
                    'sort_order' => $definition['verified'] ? 1 : 2,
                ],
            );

            $badge->users()->syncWithoutDetaching([
                $authors[$key]->id => [
                    'awarded_by' => $authors['content']->id,
                    'awarded_at' => now()->subDays(7),
                    'notes' => 'Seeded public author badge.',
                ],
            ]);
        }

        return $authors;
    }

    private function seedBrandMedia(User $author): void
    {
        $folder = MediaFolder::query()->updateOrCreate(
            ['parent_id' => null, 'slug' => 'brand-assets'],
            [
                'name' => 'Brand Assets',
                'path' => 'brand',
            ],
        );

        $assets = [
            'brand/scratch-learning-logo.svg' => [
                'folder_id' => $folder->id,
                'uploaded_by' => $author->id,
                'url' => '/brand/scratch-learning-logo.svg',
                'title' => 'Scratch Learning Logo',
                'alt_text' => 'Scratch Learning logo',
                'caption' => 'Primary Scratch Learning brand mark',
                'mime_type' => 'image/svg+xml',
                'size' => 8_500,
                'width' => 520,
                'height' => 128,
                'metadata' => ['seeded' => true],
            ],
            'brand/home-hero-slide-learning.png' => [
                'folder_id' => $folder->id,
                'uploaded_by' => $author->id,
                'url' => '/brand/home-hero-slide-learning.png',
                'title' => 'Scratch Learning Hero Slide',
                'alt_text' => 'Professional learner using a modern online course dashboard',
                'caption' => 'Homepage slider image for the Scratch Learning LMS',
                'mime_type' => 'image/png',
                'size' => 2_400_000,
                'width' => 1672,
                'height' => 941,
                'metadata' => ['seeded' => true, 'surface' => 'home_hero'],
            ],
            'brand/home-hero-slide-watch.png' => [
                'folder_id' => $folder->id,
                'uploaded_by' => $author->id,
                'url' => '/brand/home-hero-slide-watch.png',
                'title' => 'Course Watch Hero Slide',
                'alt_text' => 'Premium course watch interface on a modern tablet',
                'caption' => 'Homepage slider image for lesson progress and course watch',
                'mime_type' => 'image/png',
                'size' => 2_400_000,
                'width' => 1672,
                'height' => 941,
                'metadata' => ['seeded' => true, 'surface' => 'home_hero'],
            ],
            'brand/home-hero-slide-cms.png' => [
                'folder_id' => $folder->id,
                'uploaded_by' => $author->id,
                'url' => '/brand/home-hero-slide-cms.png',
                'title' => 'CMS Analytics Hero Slide',
                'alt_text' => 'Modern LMS admin dashboard with content and analytics panels',
                'caption' => 'Homepage slider image for CMS and reporting controls',
                'mime_type' => 'image/png',
                'size' => 2_400_000,
                'width' => 1672,
                'height' => 941,
                'metadata' => ['seeded' => true, 'surface' => 'home_hero'],
            ],
        ];

        foreach ($assets as $path => $asset) {
            MediaAsset::query()->updateOrCreate(
                ['disk' => 'public', 'path' => $path],
                $asset,
            );
        }
    }

    /**
     * @return array{array<string, CourseCategory>, array<string, CourseSubcategory>}
     */
    private function seedCourseTaxonomy(): array
    {
        $categoryDefinitions = [
            'software-engineering' => [
                'name' => 'Software Engineering',
                'description' => 'Structured technical training for production systems.',
                'sort_order' => 1,
                'subcategories' => [
                    'laravel-platforms' => 'Laravel Platforms',
                    'secure-architecture' => 'Secure Architecture',
                ],
            ],
            'learning-operations' => [
                'name' => 'Learning Operations',
                'description' => 'Student journeys, editorial systems, support, and retention workflows.',
                'sort_order' => 2,
                'subcategories' => [
                    'student-success' => 'Student Success',
                    'editorial-systems' => 'Editorial Systems',
                ],
            ],
            'business-growth' => [
                'name' => 'Business Growth',
                'description' => 'Payments, subscriptions, pricing, campaigns, and monetization loops.',
                'sort_order' => 3,
                'subcategories' => [
                    'monetization' => 'Monetization',
                    'launch-systems' => 'Launch Systems',
                ],
            ],
            'analytics-reporting' => [
                'name' => 'Analytics & Reporting',
                'description' => 'Business intelligence for learning platforms and content teams.',
                'sort_order' => 4,
                'subcategories' => [
                    'data-intelligence' => 'Data Intelligence',
                    'retention-analysis' => 'Retention Analysis',
                ],
            ],
        ];

        $categories = [];
        $subcategories = [];

        foreach ($categoryDefinitions as $slug => $definition) {
            $category = CourseCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                    'seo_title' => "{$definition['name']} Courses",
                    'seo_description' => $definition['description'],
                    'schema' => ['@type' => 'CollectionPage'],
                ],
            );

            $categories[$slug] = $category;

            foreach ($definition['subcategories'] as $subcategorySlug => $subcategoryName) {
                $subcategories[$subcategorySlug] = CourseSubcategory::query()->updateOrCreate(
                    ['course_category_id' => $category->id, 'slug' => $subcategorySlug],
                    [
                        'name' => $subcategoryName,
                        'description' => "{$subcategoryName} training for operational learning teams.",
                        'sort_order' => count($subcategories) + 1,
                        'is_active' => true,
                        'seo_title' => "{$subcategoryName} Courses",
                        'seo_description' => "Practical {$subcategoryName} courses for production-ready teams.",
                        'schema' => ['@type' => 'CollectionPage'],
                    ],
                );
            }
        }

        return [$categories, $subcategories];
    }

    /**
     * @param  array<string, User>  $authors
     * @param  array<string, CourseCategory>  $categories
     * @param  array<string, CourseSubcategory>  $subcategories
     * @return array<string, Course>
     */
    private function seedCourses(array $authors, array $categories, array $subcategories): array
    {
        $definitions = [
            [
                'slug' => 'industrial-laravel-foundations',
                'title' => 'Industrial Laravel Foundations',
                'author' => 'content',
                'category' => 'software-engineering',
                'subcategory' => 'laravel-platforms',
                'level' => 'intermediate',
                'price' => 149,
                'is_free' => false,
                'days_ago' => 12,
                'short' => 'Build a modular Laravel learning platform with durable content, permissions, and public APIs.',
                'description' => 'Design a Laravel platform with clear modules, roles, publishing workflows, public APIs, audit trails, and extension points for payments and community features.',
                'focus' => ['Domain boundaries', 'Admin operations', 'Public delivery', 'Industrial scalability'],
            ],
            [
                'slug' => 'paddle-commerce-for-course-platforms',
                'title' => 'Paddle Commerce for Course Platforms',
                'author' => 'zara',
                'category' => 'business-growth',
                'subcategory' => 'monetization',
                'level' => 'advanced',
                'price' => 199,
                'is_free' => false,
                'days_ago' => 10,
                'short' => 'Connect products, prices, checkout, subscriptions, discounts, and entitlement access.',
                'description' => 'Plan a payment layer that keeps Paddle orders, subscriptions, webhook events, audit logs, and course access decisions aligned.',
                'focus' => ['Checkout sessions', 'Subscriptions', 'Entitlements', 'Revenue recovery'],
            ],
            [
                'slug' => 'student-experience-and-progress-systems',
                'title' => 'Student Experience and Progress Systems',
                'author' => 'maya',
                'category' => 'learning-operations',
                'subcategory' => 'student-success',
                'level' => 'intermediate',
                'price' => 129,
                'is_free' => false,
                'days_ago' => 8,
                'short' => 'Create progress tracking, continue-watching, notes, bookmarks, certificates, and drip learning.',
                'description' => 'Shape a student workspace that makes learning feel continuous, protected, measurable, and motivating across lessons and courses.',
                'focus' => ['Progress tracking', 'Protected downloads', 'Certificates', 'Drip content'],
            ],
            [
                'slug' => 'analytics-reporting-for-learning-products',
                'title' => 'Analytics Reporting for Learning Products',
                'author' => 'noah',
                'category' => 'analytics-reporting',
                'subcategory' => 'data-intelligence',
                'level' => 'advanced',
                'price' => 179,
                'is_free' => false,
                'days_ago' => 6,
                'short' => 'Model engagement, completion, revenue, cohorts, funnels, and author contribution dashboards.',
                'description' => 'Build reports that connect landing page traffic, checkout conversion, learning completion, creator output, and subscription revenue.',
                'focus' => ['Funnels', 'Cohorts', 'Revenue reporting', 'Author performance'],
            ],
            [
                'slug' => 'editorial-workflows-for-expert-content',
                'title' => 'Editorial Workflows for Expert Content',
                'author' => 'content',
                'category' => 'learning-operations',
                'subcategory' => 'editorial-systems',
                'level' => 'beginner',
                'price' => 0,
                'is_free' => true,
                'days_ago' => 4,
                'short' => 'Run revisions, reviewer notes, scheduled publishing, badges, and quality checks.',
                'description' => 'Create an editorial operating system that supports creators, reviewers, scheduled publishing, expert labels, and future revenue share.',
                'focus' => ['Review queues', 'Scheduled publishing', 'Expert labels', 'Content quality'],
            ],
            [
                'slug' => 'community-moderation-for-paid-learning',
                'title' => 'Community Moderation for Paid Learning',
                'author' => 'maya',
                'category' => 'software-engineering',
                'subcategory' => 'secure-architecture',
                'level' => 'intermediate',
                'price' => 99,
                'is_free' => false,
                'days_ago' => 2,
                'short' => 'Launch Q&A, discussions, reactions, groups, spam checks, reports, and moderation queues.',
                'description' => 'Add community engagement with moderation-first workflows so Q&A, forums, reactions, private groups, and reports remain trustworthy.',
                'focus' => ['Q&A', 'Moderation queues', 'Reputation', 'Private groups'],
            ],
        ];

        $courses = [];

        foreach ($definitions as $definition) {
            $course = Course::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'course_category_id' => $categories[$definition['category']]->id,
                    'course_subcategory_id' => $subcategories[$definition['subcategory']]->id,
                    'created_by' => $authors[$definition['author']]->id,
                    'thumbnail_media_id' => null,
                    'title' => $definition['title'],
                    'short_description' => $definition['short'],
                    'description' => $definition['description'],
                    'intro_video_url' => "https://videos.example.com/{$definition['slug']}",
                    'level' => $definition['level'],
                    'language' => 'en',
                    'price' => $definition['price'],
                    'is_free' => $definition['is_free'],
                    'status' => PublishStatus::Published,
                    'published_at' => now()->subDays($definition['days_ago']),
                    'seo_title' => $definition['title'],
                    'seo_description' => $definition['short'],
                    'schema' => [
                        '@type' => 'Course',
                        'name' => $definition['title'],
                        'courseMode' => 'online',
                    ],
                ],
            );

            $this->seedCourseCurriculum($course, $definition['focus']);
            $this->seedCourseFaqs($course, $definition['title'], $definition['is_free']);

            $courses[$definition['slug']] = $course;
        }

        return $courses;
    }

    /**
     * @param  array<int, string>  $focusAreas
     */
    private function seedCourseCurriculum(Course $course, array $focusAreas): void
    {
        $publishedAt = $course->getAttribute('published_at');

        if ($course->slug === 'industrial-laravel-foundations') {
            CourseLesson::query()
                ->where('course_id', $course->id)
                ->whereIn('slug', ['content-domain-modeling'])
                ->delete();

            CourseSection::query()
                ->where('course_id', $course->id)
                ->whereIn('slug', ['foundation-architecture'])
                ->delete();
        }

        $sections = [
            [
                'slug' => 'operating-model',
                'title' => 'Operating Model',
                'description' => 'Set the core decisions, actors, workflow boundaries, and launch assumptions.',
            ],
            [
                'slug' => 'production-rollout',
                'title' => 'Production Rollout',
                'description' => 'Turn the concept into implementation steps, QA checks, and operational reporting.',
            ],
        ];

        foreach ($sections as $sectionIndex => $sectionDefinition) {
            $section = CourseSection::query()->updateOrCreate(
                ['course_id' => $course->id, 'slug' => $sectionDefinition['slug']],
                [
                    'title' => $sectionDefinition['title'],
                    'description' => $sectionDefinition['description'],
                    'sort_order' => $sectionIndex + 1,
                    'status' => PublishStatus::Published,
                    'published_at' => $publishedAt,
                ],
            );

            for ($lessonIndex = 1; $lessonIndex <= 2; $lessonIndex++) {
                $absoluteLesson = ($sectionIndex * 2) + $lessonIndex;
                $focus = $focusAreas[($absoluteLesson - 1) % count($focusAreas)];
                $isFree = $absoluteLesson === 1;
                $lessonSlug = str("{$sectionDefinition['slug']} {$absoluteLesson} {$focus}")
                    ->slug()
                    ->toString();

                CourseLesson::query()->updateOrCreate(
                    ['course_id' => $course->id, 'slug' => $lessonSlug],
                    [
                        'course_section_id' => $section->id,
                        'title' => "{$focus}: Lesson {$absoluteLesson}",
                        'order_number' => $absoluteLesson,
                        'content' => "This lesson turns {$focus} into a practical workflow for {$course->title}. You will map decisions, identify operational risks, and define a clean implementation path your team can reuse.",
                        'video_type' => VideoType::Url,
                        'video_url' => "https://videos.example.com/{$course->slug}/lesson-{$absoluteLesson}",
                        'is_free' => $isFree,
                        'is_paid' => ! $isFree,
                        'preview_word_limit' => $isFree ? null : 28,
                        'allow_comments' => true,
                        'allow_questions' => true,
                        'status' => PublishStatus::Published,
                        'published_at' => $publishedAt,
                        'seo_title' => "{$focus}: {$course->title}",
                        'seo_description' => "Learn {$focus} inside {$course->title}.",
                        'schema' => ['@type' => 'LearningResource'],
                    ],
                );
            }
        }

        CourseFaq::query()->where('course_id', $course->id)->whereNotNull('course_lesson_id')->delete();

        CourseFaq::query()->updateOrCreate(
            ['course_id' => $course->id, 'course_lesson_id' => null, 'question' => "What will I build in {$course->title}?"],
            [
                'answer' => 'You will produce practical operating artifacts: models, workflows, policies, reporting slices, and launch-ready implementation decisions.',
                'sort_order' => 1,
                'status' => PublishStatus::Published,
                'published_at' => $publishedAt,
            ],
        );
    }

    private function seedCourseFaqs(Course $course, string $title, bool $isFree): void
    {
        $publishedAt = $course->getAttribute('published_at');

        $faqs = [
            [
                'question' => "Is {$title} beginner friendly?",
                'answer' => $isFree
                    ? 'Yes. This course is designed as a low-friction starting point for teams reviewing the platform.'
                    : 'It is practical and guided, but it assumes you understand the basics of web platforms and learning products.',
                'sort_order' => 2,
            ],
            [
                'question' => 'Can teams use this material in production planning?',
                'answer' => 'Yes. The examples are shaped around industrial-friendly architecture, operational checks, and workflows a real team can maintain.',
                'sort_order' => 3,
            ],
        ];

        foreach ($faqs as $faq) {
            CourseFaq::query()->updateOrCreate(
                ['course_id' => $course->id, 'course_lesson_id' => null, 'question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'sort_order' => $faq['sort_order'],
                    'status' => PublishStatus::Published,
                    'published_at' => $publishedAt,
                ],
            );
        }
    }

    /**
     * @return array{array<string, BlogCategory>, array<string, BlogTag>}
     */
    private function seedBlogTaxonomy(): array
    {
        $categoryDefinitions = [
            'engineering' => 'Architecture notes and technical learning guides.',
            'student-success' => 'Retention, support, progress, and student operations.',
            'growth' => 'Offers, subscriptions, referrals, email, and pricing strategy.',
            'analytics' => 'Reporting, funnels, cohorts, and business intelligence.',
        ];

        $tagDefinitions = [
            'laravel' => 'Laravel',
            'paddle' => 'Paddle',
            'analytics' => 'Analytics',
            'community' => 'Community',
            'seo' => 'SEO',
            'editorial' => 'Editorial',
        ];

        $categories = [];
        $tags = [];

        foreach ($categoryDefinitions as $slug => $description) {
            $categories[$slug] = BlogCategory::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => str($slug)->replace('-', ' ')->title(),
                    'description' => $description,
                    'is_active' => true,
                    'seo_title' => str($slug)->replace('-', ' ')->title().' Blog',
                    'seo_description' => $description,
                    'schema' => ['@type' => 'Blog'],
                ],
            );
        }

        foreach ($tagDefinitions as $slug => $name) {
            $tags[$slug] = BlogTag::query()->updateOrCreate(
                ['slug' => $slug],
                ['name' => $name],
            );
        }

        return [$categories, $tags];
    }

    /**
     * @param  array<string, User>  $authors
     * @param  array<string, BlogCategory>  $categories
     * @param  array<string, BlogTag>  $tags
     */
    private function seedBlogPosts(array $authors, array $categories, array $tags): void
    {
        $definitions = [
            [
                'slug' => 'building-industrial-learning-platforms',
                'title' => 'Building Industrial Learning Platforms',
                'author' => 'content',
                'category' => 'engineering',
                'tags' => ['laravel', 'editorial', 'seo'],
                'featured' => true,
                'days_ago' => 11,
                'excerpt' => 'How content structure, publishing, SEO, and admin operations support long-term learning product growth.',
                'content' => 'Start with durable models, explicit workflows, and public read APIs. Admin UI, payments, reporting, community, and AI features can then evolve without disturbing the content core.',
            ],
            [
                'slug' => 'how-to-price-premium-course-libraries',
                'title' => 'How to Price Premium Course Libraries',
                'author' => 'zara',
                'category' => 'growth',
                'tags' => ['paddle', 'seo'],
                'featured' => true,
                'days_ago' => 9,
                'excerpt' => 'A practical pricing model for paid courses, subscription libraries, bundles, and launch discounts.',
                'content' => 'Pricing works best when the customer understands the outcome, the upgrade path is obvious, and discounts are tracked through clean campaign attribution.',
            ],
            [
                'slug' => 'what-students-need-after-enrollment',
                'title' => 'What Students Need After Enrollment',
                'author' => 'maya',
                'category' => 'student-success',
                'tags' => ['editorial', 'community'],
                'featured' => false,
                'days_ago' => 7,
                'excerpt' => 'Progress dashboards, notes, bookmarks, protected downloads, and support loops that keep students moving.',
                'content' => 'The first post-purchase experience should reduce uncertainty. Continue-watching, clear lesson locks, resources, reminders, and useful community prompts all matter.',
            ],
            [
                'slug' => 'reporting-that-improves-course-completion',
                'title' => 'Reporting That Improves Course Completion',
                'author' => 'noah',
                'category' => 'analytics',
                'tags' => ['analytics'],
                'featured' => false,
                'days_ago' => 5,
                'excerpt' => 'Completion reports are most useful when they expose specific drop-off points and next actions.',
                'content' => 'A useful learning dashboard connects cohorts, lessons, time windows, revenue, and author contribution so operators can decide what to improve first.',
            ],
            [
                'slug' => 'editorial-review-systems-for-experts',
                'title' => 'Editorial Review Systems for Experts',
                'author' => 'content',
                'category' => 'engineering',
                'tags' => ['editorial', 'seo'],
                'featured' => false,
                'days_ago' => 3,
                'excerpt' => 'Reviewer notes, scheduled publishing, verified badges, and revision history make expert content safer to scale.',
                'content' => 'Expert content needs respect and structure. Revisions, approval history, comments, and scheduled releases help teams maintain quality without slowing publishing to a crawl.',
            ],
            [
                'slug' => 'community-moderation-before-scale',
                'title' => 'Community Moderation Before Scale',
                'author' => 'maya',
                'category' => 'student-success',
                'tags' => ['community'],
                'featured' => true,
                'days_ago' => 1,
                'excerpt' => 'Q&A and discussion features are powerful only when moderation, reports, and spam checks are designed first.',
                'content' => 'A learning community needs accepted answers, blocked words, reputation, reports, and private member spaces before traffic spikes. Trust is infrastructure.',
            ],
        ];

        foreach ($definitions as $definition) {
            $post = BlogPost::query()->updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'blog_category_id' => $categories[$definition['category']]->id,
                    'author_id' => $authors[$definition['author']]->id,
                    'featured_image_media_id' => null,
                    'title' => $definition['title'],
                    'excerpt' => $definition['excerpt'],
                    'content' => $definition['content'],
                    'status' => PublishStatus::Published,
                    'published_at' => now()->subDays($definition['days_ago']),
                    'is_featured' => $definition['featured'],
                    'seo_title' => $definition['title'],
                    'seo_description' => $definition['excerpt'],
                    'schema' => ['@type' => 'BlogPosting'],
                ],
            );

            $post->tags()->sync(collect($definition['tags'])->map(fn (string $slug): int => $tags[$slug]->id)->all());

            BlogFaq::query()->updateOrCreate(
                ['blog_post_id' => $post->id, 'question' => 'How should I apply this idea?'],
                [
                    'answer' => 'Start with one workflow, measure the effect, then expand it once your team can maintain it consistently.',
                    'sort_order' => 1,
                    'status' => PublishStatus::Published,
                    'published_at' => $post->published_at,
                ],
            );
        }
    }

    private function seedPages(User $author): void
    {
        $pages = [
            'about' => [
                'title' => 'About Scratch Learning',
                'excerpt' => 'Industrial-friendly technical learning for teams that need real operating skill.',
                'content' => 'Scratch Learning helps teams move from scattered tutorials to structured, measurable, production-ready education.',
                'sort_order' => 1,
                'blocks' => [
                    'mission' => [
                        'title' => 'Mission',
                        'body' => 'Build reliable skills for reliable systems through courses, editorial insight, analytics, and thoughtful community.',
                    ],
                    'standards' => [
                        'title' => 'How We Work',
                        'body' => 'Every learning path is designed around maintainable architecture, clear permissions, publish workflows, and business reporting.',
                    ],
                ],
            ],
            'pricing' => [
                'title' => 'Pricing',
                'excerpt' => 'Simple options for individuals, teams, and business learning programs.',
                'content' => 'Start with free editorial courses, buy focused premium courses, or move into subscription access as your team grows.',
                'sort_order' => 2,
                'blocks' => [
                    'plans' => [
                        'title' => 'Plans',
                        'body' => 'Individual courses, premium library access, business seats, launch offers, and future team subscriptions are prepared in the commerce model.',
                    ],
                ],
            ],
            'contact' => [
                'title' => 'Contact',
                'excerpt' => 'Talk to the Scratch Learning team about course access, business plans, or creator partnerships.',
                'content' => 'Use the platform contact channel for learner support, instructor onboarding, and business learning discussions.',
                'sort_order' => 3,
                'blocks' => [
                    'support' => [
                        'title' => 'Support',
                        'body' => 'Learner help, business onboarding, editorial partnerships, and payment questions are routed through the admin workflow.',
                    ],
                ],
            ],
            'faq' => [
                'title' => 'FAQ',
                'excerpt' => 'Answers for students, instructors, teams, and business buyers.',
                'content' => 'Use this page to maintain platform-level questions about course access, certificates, billing, support, and instructor applications.',
                'sort_order' => 4,
                'blocks' => [
                    'student-access' => [
                        'title' => 'How do students access paid courses?',
                        'body' => 'Paid lessons are protected through the entitlement service. Free previews remain available while premium lessons require course purchase or subscription access.',
                    ],
                    'certificates' => [
                        'title' => 'Can certificates be verified publicly?',
                        'body' => 'Yes. Completed courses can issue certificates with public verification URLs through the certificate verification workflow.',
                    ],
                ],
            ],
            'become-an-instructor' => [
                'title' => 'Become an Instructor',
                'excerpt' => 'Apply to publish expert courses and editorial content on Scratch Learning.',
                'content' => 'Instructor and creator profiles are reviewed before publishing. Approved creators can prepare courses and blogs, submit revisions, receive reviewer comments, and gain public profile visibility through approved educational content.',
                'sort_order' => 5,
                'blocks' => [
                    'review' => [
                        'title' => 'Review Process',
                        'body' => 'Applications are checked for expertise, portfolio quality, audience fit, and operational readiness before an instructor profile is approved.',
                    ],
                    'creator-visibility' => [
                        'title' => 'Creator Visibility',
                        'body' => 'Scratch Learning does not pay creators, instructors, or bloggers in this creator program. Published creator work is promotional and educational, and approved profiles may show LinkedIn, website, or public profile links.',
                    ],
                ],
            ],
            'testimonials' => [
                'title' => 'Testimonials',
                'excerpt' => 'Proof points from students, teams, and instructors using the platform.',
                'content' => 'Use testimonials to show course outcomes, team enablement stories, and instructor credibility signals.',
                'sort_order' => 6,
                'blocks' => [
                    'student' => [
                        'title' => 'Student Story',
                        'body' => 'The course structure made it clear what to watch next, what was locked, and how progress connected to the final certificate.',
                    ],
                    'team' => [
                        'title' => 'Team Story',
                        'body' => 'The analytics and editorial workflow helped our team turn training into a measurable operating system.',
                    ],
                ],
            ],
            'privacy-policy' => [
                'title' => 'Privacy Policy',
                'excerpt' => 'How Scratch Learning treats learning, payment, analytics, and community data.',
                'content' => 'Scratch Learning stores account, learning, creator, payment, analytics, and community information needed to operate the LMS. This includes creator agreement acceptance records such as terms version, accepted time, IP address, and user agent. This CMS-managed policy should still receive legal review before production launch.',
                'sort_order' => 7,
                'blocks' => [
                    'data' => [
                        'title' => 'Data Use',
                        'body' => 'The platform separates account, payment, content, analytics, and community records so access and retention policies can stay clear.',
                    ],
                    'creator-profile-links' => [
                        'title' => 'Creator Profile Links',
                        'body' => 'When creators submit courses or blogs, Scratch Learning may show public profile, LinkedIn, website, bio, badge, and expertise information on approved course, lesson, blog, and creator profile pages.',
                    ],
                    'agreement-records' => [
                        'title' => 'Agreement Records',
                        'body' => 'Creator agreement acceptance is versioned and recorded with accepted_at, ip_address, and user_agent fields so admin teams can prove which creator terms were accepted before content submission.',
                    ],
                ],
            ],
            'terms-and-conditions' => [
                'title' => 'Terms & Conditions',
                'excerpt' => 'Platform rules for learners, creators, teams, and advertisers.',
                'content' => 'Scratch Learning provides educational courses, blogs, community features, and paid learning access. Scratch Learning does not pay creators, instructors, or bloggers who submit content under this creator program. Submitted and published creator content is promotional and educational. These CMS-managed terms should receive legal review before production launch.',
                'sort_order' => 8,
                'blocks' => [
                    'usage' => [
                        'title' => 'Acceptable Use',
                        'body' => 'Students and creators should use course, community, and payment features in line with platform policies and moderation rules.',
                    ],
                    'no-creator-payments' => [
                        'title' => 'No Creator Payments',
                        'body' => 'Scratch Learning does not pay creators, instructors, or bloggers for uploaded, submitted, approved, or published courses, lessons, blogs, files, or resources under this creator program.',
                    ],
                    'promotional-content' => [
                        'title' => 'Promotional Educational Content',
                        'body' => 'Published creator content is promotional and educational. Scratch Learning may display creator LinkedIn, website, public profile, bio, badges, expertise, and other approved profile links with blogs and courses.',
                    ],
                    'creator-content-rights' => [
                        'title' => 'Creator Content Rights',
                        'body' => 'Creators must own, create, license, or otherwise have the required rights to every course, lesson, video, image, document, blog, attachment, and downloadable resource they upload or submit.',
                    ],
                ],
            ],
            'cart' => [
                'title' => 'Cart',
                'excerpt' => 'Review selected courses, bundles, discounts, and membership offers.',
                'content' => 'The cart page is prepared as a CMS-backed route while the Paddle checkout flow handles real order creation and paid entitlement activation.',
                'sort_order' => 9,
                'blocks' => [
                    'checkout' => [
                        'title' => 'Checkout Flow',
                        'body' => 'Courses and subscription offers connect to Paddle checkout sessions once the payment product and price records are configured.',
                    ],
                ],
            ],
            'checkout' => [
                'title' => 'Checkout',
                'excerpt' => 'A secure payment handoff for course purchases and premium memberships.',
                'content' => 'Real checkout actions are created through the payment backend. This page gives the template URL a clean backend-backed destination.',
                'sort_order' => 10,
                'blocks' => [
                    'paddle' => [
                        'title' => 'Paddle Ready',
                        'body' => 'Checkout sessions, orders, subscriptions, audit logs, and entitlements are modeled in the payment domain.',
                    ],
                ],
            ],
        ];

        foreach ($pages as $slug => $definition) {
            $page = Page::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'author_id' => $author->id,
                    'title' => $definition['title'],
                    'excerpt' => $definition['excerpt'],
                    'content' => $definition['content'],
                    'template' => 'default',
                    'status' => PublishStatus::Published,
                    'published_at' => now()->subDays(14 - $definition['sort_order']),
                    'sort_order' => $definition['sort_order'],
                    'seo_title' => $definition['title'],
                    'seo_description' => $definition['excerpt'],
                    'schema' => ['@type' => $slug === 'about' ? 'AboutPage' : 'WebPage'],
                ],
            );

            ContentBlock::query()
                ->where('page_id', $page->id)
                ->whereNotIn('key', array_keys($definition['blocks']))
                ->update(['is_active' => false]);

            foreach ($definition['blocks'] as $key => $block) {
                ContentBlock::query()->updateOrCreate(
                    ['page_id' => $page->id, 'key' => $key],
                    [
                        'title' => $block['title'],
                        'body' => $block['body'],
                        'settings' => ['width' => 'contained'],
                        'sort_order' => array_search($key, array_keys($definition['blocks']), true) + 1,
                        'is_active' => true,
                    ],
                );
            }
        }
    }

    private function seedMenus(): void
    {
        $menu = Menu::query()->updateOrCreate(
            ['slug' => 'main-navigation'],
            [
                'name' => 'Main Navigation',
                'location' => 'header',
                'is_active' => true,
            ],
        );

        $items = [
            ['title' => 'Courses', 'type' => 'url', 'url' => '/courses', 'sort_order' => 1],
            ['title' => 'Blog', 'type' => 'url', 'url' => '/blog', 'sort_order' => 2],
            ['title' => 'Instructors', 'type' => 'url', 'url' => '/instructors', 'sort_order' => 3],
            ['title' => 'Pricing', 'type' => 'page', 'url' => '/pricing-plan', 'page' => 'pricing', 'sort_order' => 4],
            ['title' => 'FAQ', 'type' => 'page', 'url' => '/faq', 'page' => 'faq', 'sort_order' => 5],
            ['title' => 'Become Instructor', 'type' => 'page', 'url' => '/become-an-instructor', 'page' => 'become-an-instructor', 'sort_order' => 6],
            ['title' => 'About', 'type' => 'page', 'url' => '/about-us', 'page' => 'about', 'sort_order' => 7],
            ['title' => 'Contact', 'type' => 'page', 'url' => '/contact-us', 'page' => 'contact', 'sort_order' => 8],
        ];

        foreach ($items as $item) {
            MenuItem::query()->updateOrCreate(
                ['menu_id' => $menu->id, 'title' => $item['title']],
                [
                    'page_id' => isset($item['page']) ? Page::query()->where('slug', $item['page'])->value('id') : null,
                    'type' => $item['type'],
                    'url' => $item['url'],
                    'sort_order' => $item['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    private function seedSiteSettings(): void
    {
        $settings = [
            'seo.default_title' => [
                'group' => 'seo',
                'value' => ['content' => 'Scratch Learning'],
            ],
            'seo.default_description' => [
                'group' => 'seo',
                'value' => ['content' => 'Industrial-friendly online courses, expert articles, and learning operations for modern teams.'],
            ],
            'brand.primary_cta' => [
                'group' => 'brand',
                'value' => ['label' => 'Explore courses', 'url' => '/courses'],
            ],
            'home.hero' => [
                'group' => 'home',
                'value' => [
                    'mode' => 'slider',
                    'eyebrow' => 'The leader in online learning',
                    'heading' => 'Find the best courses from expert mentors.',
                    'highlight_terms' => ['courses', 'mentors'],
                    'description' => 'Industrial-friendly training, blogs, and operational learning content for modern teams.',
                    'search_enabled' => true,
                    'stats_enabled' => true,
                    'featured_course_enabled' => true,
                ],
            ],
        ];

        foreach ($settings as $key => $setting) {
            SiteSetting::query()->updateOrCreate(
                ['key' => $key],
                [
                    'group' => $setting['group'],
                    'value' => $setting['value'],
                    'is_encrypted' => false,
                ],
            );
        }
    }

    private function seedHomeHeroSlides(): void
    {
        $slides = [
            [
                'media_path' => 'brand/home-hero-slide-learning.png',
                'eyebrow' => 'New learning paths',
                'title' => 'Build production skills with expert-led courses.',
                'subtitle' => 'Launch structured training for teams, creators, and serious students with protected lessons and measurable progress.',
                'button_label' => 'Explore courses',
                'target_url' => '/courses',
                'sort_order' => 1,
                'text_position' => 'left',
            ],
            [
                'media_path' => 'brand/home-hero-slide-watch.png',
                'eyebrow' => 'Continue watching',
                'title' => 'Keep learners moving from lesson to certificate.',
                'subtitle' => 'Notes, bookmarks, Q&A, resources, quizzes, and progress tracking are ready for a premium LMS experience.',
                'button_label' => 'View course watch',
                'target_url' => '/course-watch',
                'sort_order' => 2,
                'text_position' => 'left',
            ],
            [
                'media_path' => 'brand/home-hero-slide-cms.png',
                'eyebrow' => 'Creator-ready CMS',
                'title' => 'Manage courses, blogs, pages, and homepage slides.',
                'subtitle' => 'Use backend controls to switch between slider mode and the original Scratch Learning hero design.',
                'button_label' => 'Open admin CMS',
                'target_url' => '/admin/cms/home-hero',
                'sort_order' => 3,
                'text_position' => 'left',
            ],
        ];

        foreach ($slides as $slide) {
            $media = MediaAsset::query()
                ->where('disk', 'public')
                ->where('path', $slide['media_path'])
                ->first();

            HomeHeroSlide::query()->updateOrCreate(
                ['title' => $slide['title']],
                [
                    'media_asset_id' => $media?->id,
                    'eyebrow' => $slide['eyebrow'],
                    'subtitle' => $slide['subtitle'],
                    'button_label' => $slide['button_label'],
                    'target_url' => $slide['target_url'],
                    'image_url' => $media?->url,
                    'image_alt' => $media?->alt_text,
                    'text_position' => $slide['text_position'],
                    'sort_order' => $slide['sort_order'],
                    'is_active' => true,
                    'opens_in_new_tab' => false,
                    'metadata' => ['seeded' => true],
                ],
            );
        }
    }

    private function seedHomePageSections(): void
    {
        $sections = [
            [
                'key' => 'course-categories',
                'type' => HomePageSectionType::CourseCategories,
                'eyebrow' => 'Course Categories',
                'title' => 'Explore skills by discipline.',
                'subtitle' => 'Help students jump straight into the topic they need, from engineering to growth and analytics.',
                'cta_label' => 'Browse all categories',
                'cta_url' => '/courses',
                'background' => 'soft',
                'sort_order' => 10,
                'payload' => ['limit' => 8],
            ],
            [
                'key' => 'featured-courses',
                'type' => HomePageSectionType::FeaturedCourses,
                'eyebrow' => 'Featured Courses',
                'title' => 'Production-ready courses for serious learners.',
                'subtitle' => 'Show the strongest courses from your backend catalog with price, level, lessons, and category signals.',
                'cta_label' => 'View all courses',
                'cta_url' => '/courses',
                'background' => 'white',
                'sort_order' => 20,
                'payload' => ['limit' => 6],
            ],
            [
                'key' => 'learning-paths',
                'type' => HomePageSectionType::LearningPaths,
                'eyebrow' => 'Learning Paths',
                'title' => 'Guide students through complete outcomes.',
                'subtitle' => 'Bundle courses into practical tracks so learners know what to take next and why it matters.',
                'cta_label' => 'Start with courses',
                'cta_url' => '/courses',
                'background' => 'soft',
                'sort_order' => 30,
                'payload' => ['limit' => 3],
            ],
            [
                'key' => 'why-scratch-learning',
                'type' => HomePageSectionType::WhyScratchLearning,
                'eyebrow' => 'Why Scratch Learning',
                'title' => 'Built for paid content, quality control, and measurable progress.',
                'subtitle' => 'From first visit to paid enrollment and lesson completion, every workflow supports a serious learning business.',
                'cta_label' => 'Read field notes',
                'cta_url' => '/blog',
                'background' => 'dark',
                'sort_order' => 40,
                'payload' => [
                    'cards' => [
                        ['icon' => 'circle-play', 'title' => 'Continue watching', 'body' => 'Progress, saved lessons, notes, resources, quizzes, and certificates feel connected instead of scattered.'],
                        ['icon' => 'badge-check', 'title' => 'Editorial trust', 'body' => 'Approvals, revisions, badges, and verified experts make public content feel credible.'],
                        ['icon' => 'users', 'title' => 'Teams and seats', 'body' => 'Business plans and subscriptions support cohorts, teams, and premium libraries.'],
                        ['icon' => 'chart', 'title' => 'Reporting pro', 'body' => 'Funnels, cohorts, revenue, reconciliation, and author contribution reports are part of the product story.'],
                    ],
                ],
            ],
            [
                'key' => 'testimonials',
                'type' => HomePageSectionType::Testimonials,
                'eyebrow' => 'Testimonials',
                'title' => 'Trusted by learners, operators, and instructors.',
                'subtitle' => 'Use this CMS payload to manage social proof, outcome stories, ratings, and customer roles.',
                'background' => 'white',
                'sort_order' => 50,
                'payload' => [
                    'items' => [
                        ['name' => 'Ayesha Khan', 'role' => 'Learning Operations Lead', 'quote' => 'Scratch Learning gave our team one place for courses, approvals, and measurable progress.', 'rating' => 5],
                        ['name' => 'Daniel Reed', 'role' => 'Engineering Manager', 'quote' => 'The course structure feels practical. Lessons, resources, and quizzes are easy to follow.', 'rating' => 5],
                        ['name' => 'Maya Thomas', 'role' => 'Instructor', 'quote' => 'The editorial workflow makes it much easier to publish expert content without losing quality.', 'rating' => 5],
                    ],
                ],
            ],
            [
                'key' => 'latest-blogs',
                'type' => HomePageSectionType::LatestBlogs,
                'eyebrow' => 'Latest Blogs',
                'title' => 'Field notes from approved experts.',
                'subtitle' => 'Fresh articles help students choose paths, trust authors, and keep learning between courses.',
                'cta_label' => 'Read more',
                'cta_url' => '/blog',
                'background' => 'soft',
                'sort_order' => 60,
                'payload' => ['limit' => 3],
            ],
            [
                'key' => 'homepage-faq',
                'type' => HomePageSectionType::Faq,
                'eyebrow' => 'FAQ',
                'title' => 'Clear answers before checkout.',
                'subtitle' => 'Frequently asked questions stay clear for students and search-friendly for discovery.',
                'background' => 'white',
                'sort_order' => 70,
                'payload' => ['limit' => 5],
            ],
            [
                'key' => 'newsletter-lead-magnet',
                'type' => HomePageSectionType::NewsletterLeadMagnet,
                'eyebrow' => 'Newsletter',
                'title' => 'Get the practical learning operations checklist.',
                'subtitle' => 'Capture leads with a useful free resource and connect them to the right course or membership offer.',
                'cta_label' => 'Get the checklist',
                'background' => 'accent',
                'sort_order' => 80,
                'payload' => ['button_label' => 'Send me the checklist'],
            ],
            [
                'key' => 'final-cta',
                'type' => HomePageSectionType::FinalCta,
                'eyebrow' => 'Start Learning',
                'title' => 'Ready for courses, commerce, and community.',
                'subtitle' => 'Browse practical courses, learn from approved experts, and grow into premium plans when your team is ready.',
                'cta_label' => 'Explore catalog',
                'cta_url' => '/courses',
                'background' => 'dark',
                'sort_order' => 90,
                'payload' => [
                    'metrics' => [
                        ['metric' => '6+', 'label' => 'Published courses'],
                        ['metric' => '3', 'label' => 'Learning paths'],
                        ['metric' => '24/7', 'label' => 'Self-paced access'],
                    ],
                ],
            ],
        ];

        foreach ($sections as $section) {
            $section = array_replace([
                'body' => null,
                'cta_label' => null,
                'cta_url' => null,
            ], $section);

            HomePageSection::query()->updateOrCreate(
                ['key' => $section['key']],
                [
                    'type' => $section['type'],
                    'eyebrow' => $section['eyebrow'],
                    'title' => $section['title'],
                    'subtitle' => $section['subtitle'],
                    'body' => $section['body'],
                    'cta_label' => $section['cta_label'],
                    'cta_url' => $section['cta_url'],
                    'background' => $section['background'],
                    'sort_order' => $section['sort_order'],
                    'is_active' => true,
                    'payload' => $section['payload'],
                ],
            );
        }
    }
}
