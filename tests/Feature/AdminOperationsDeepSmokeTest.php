<?php

namespace Tests\Feature;

use App\Enums\AdCampaignStatus;
use App\Enums\AdCreativeStatus;
use App\Enums\AdCreativeType;
use App\Enums\AdPricingModel;
use App\Enums\AdZoneStatus;
use App\Enums\BloggerStatus;
use App\Enums\CommunityContentStatus;
use App\Enums\CommunityVisibility;
use App\Enums\GrowthStatus;
use App\Enums\HomePageSectionType;
use App\Enums\InstructorProfileStatus;
use App\Enums\MediaVisibility;
use App\Enums\PaymentBillingInterval;
use App\Enums\PaymentProductStatus;
use App\Enums\PaymentProductType;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Enums\VideoType;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use App\Models\AdPricingSetting;
use App\Models\AdZone;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CommunityGroup;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\DiscussionForum;
use App\Models\HomeHeroSlide;
use App\Models\HomePageSection;
use App\Models\InstructorProfile;
use App\Models\LeadMagnet;
use App\Models\MediaAsset;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PaymentPrice;
use App\Models\PaymentProduct;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOperationsDeepSmokeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'deep-admin@example.com',
        ]);
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    #[DataProvider('adminOperationPageProvider')]
    public function test_every_admin_operation_page_renders_a_diagnostic_contract(string $resource, string $path): void
    {
        $response = $this->actingAs($this->admin)->get($path);

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('resource', $resource)
                ->where('basePath', $path)
                ->has('columns')
                ->has('fields')
                ->has('filters')
                ->has('rows.data')
                ->has('bulkActions')
                ->has('mediaAssets')
                ->has('metrics'),
            );

        $props = $this->inertiaProps($response);

        $this->assertNotEmpty($props['columns'], "{$resource} should expose table columns.");
        $this->assertNotEmpty($props['fields'], "{$resource} should expose form fields.");
    }

    public function test_lesson_new_form_contract_can_create_update_and_delete_a_lesson(): void
    {
        $course = Course::factory()->create([
            'title' => 'Deep Smoke Course',
        ]);

        $response = $this->actingAs($this->admin)->get('/admin/lessons');
        $response->assertOk();

        $fields = collect($this->inertiaProps($response)['fields'])->pluck('key')->all();

        $this->assertContains('course_id', $fields);
        $this->assertContains('title', $fields);
        $this->assertContains('video_type', $fields);
        $this->assertContains('status', $fields);
        $this->assertContains('is_free', $fields);
        $this->assertContains('is_paid', $fields);

        $payload = [
            'course_id' => $course->id,
            'title' => 'Lesson New Button Smoke',
            'order_number' => 1,
            'content' => 'This lesson proves the admin New form can submit.',
            'video_type' => VideoType::Url->value,
            'video_url' => 'https://videos.example.com/lesson-new-button-smoke',
            'is_free' => false,
            'is_paid' => true,
            'preview_word_limit' => 90,
            'status' => PublishStatus::Draft->value,
            'seo_title' => 'Lesson New Button Smoke',
            'seo_description' => 'Regression coverage for the admin lesson create flow.',
        ];

        $this->actingAs($this->admin)
            ->from('/admin/lessons')
            ->post('/admin/lessons', $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/lessons');

        $lesson = CourseLesson::query()->where('title', 'Lesson New Button Smoke')->firstOrFail();

        $this->actingAs($this->admin)
            ->from('/admin/lessons')
            ->patch('/admin/lessons/'.$lesson->id, [
                ...$payload,
                'title' => 'Lesson New Button Smoke Updated',
                'status' => PublishStatus::Published->value,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/lessons');

        $lesson->refresh();

        $this->assertSame('Lesson New Button Smoke Updated', $lesson->title);
        $this->assertSame(PublishStatus::Published, $lesson->status);
        $this->assertNotNull($lesson->published_at);

        $this->actingAs($this->admin)
            ->from('/admin/lessons')
            ->delete('/admin/lessons/'.$lesson->id)
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/lessons');

        $this->assertDatabaseMissing(CourseLesson::class, [
            'id' => $lesson->id,
        ]);
    }

    #[DataProvider('adminCrudResourceProvider')]
    public function test_admin_create_update_delete_flow_for_core_resources(string $resource): void
    {
        $path = self::adminOperationPages()[$resource];
        $modelClass = $this->modelClassFor($resource);
        $lookupColumn = $this->lookupColumnFor($resource);
        $createPayload = $this->payloadFor($resource, 'create');

        $this->actingAs($this->admin)
            ->from($path)
            ->post($path, $createPayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect($path);

        $record = $modelClass::query()
            ->where($lookupColumn, $createPayload[$lookupColumn])
            ->firstOrFail();

        if ($resource === 'courses' && $record instanceof Course) {
            $this->attachCourseCurriculum($record);
        }

        $updatePayload = $this->payloadFor($resource, 'update', $record);

        $this->actingAs($this->admin)
            ->from($path)
            ->patch($path.'/'.$record->getKey(), $updatePayload)
            ->assertSessionHasNoErrors()
            ->assertRedirect($path);

        $record->refresh();

        $this->assertSame(
            (string) $updatePayload[$lookupColumn],
            (string) $record->getAttribute($lookupColumn),
            "{$resource} should update {$lookupColumn}.",
        );

        $id = $record->getKey();

        $this->actingAs($this->admin)
            ->from($path)
            ->delete($path.'/'.$id)
            ->assertSessionHasNoErrors()
            ->assertRedirect($path);

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $this->assertSoftDeleted($modelClass, [
                'id' => $id,
            ]);
        } else {
            $this->assertDatabaseMissing($modelClass, [
                'id' => $id,
            ]);
        }
    }

    public function test_publishable_admin_bulk_status_flow_records_the_visible_state_change(): void
    {
        $course = Course::factory()->create([
            'status' => PublishStatus::Draft,
            'published_at' => null,
        ]);
        $this->attachCourseCurriculum($course);

        $this->actingAs($this->admin)
            ->from('/admin/courses')
            ->post('/admin/courses/bulk', [
                'ids' => [$course->id],
                'action' => 'status',
                'value' => PublishStatus::Published->value,
                'note' => 'Deep smoke publish.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/courses');

        $course->refresh();

        $this->assertSame(PublishStatus::Published, $course->status);
        $this->assertNotNull($course->published_at);
        $this->assertSame('Deep smoke publish.', $course->admin_notes);
    }

    public function test_homepage_section_bulk_active_state_flow_updates_visibility(): void
    {
        $section = HomePageSection::factory()->create([
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)
            ->from('/admin/cms/homepage-sections')
            ->post('/admin/cms/homepage-sections/bulk', [
                'ids' => [$section->id],
                'action' => 'active',
                'value' => 'inactive',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/cms/homepage-sections');

        $this->assertFalse($section->refresh()->is_active);
    }

    public function test_admin_operation_new_and_edit_buttons_reveal_the_form_panel(): void
    {
        $component = (string) file_get_contents(resource_path('js/pages/admin/Operations.vue'));

        $this->assertStringContainsString('ref="formPanel"', $component);
        $this->assertMatchesRegularExpression('/function openCreate\(\)[\s\S]*formOpen\.value = true;[\s\S]*scrollToForm\(\);/', $component);
        $this->assertMatchesRegularExpression('/function openEdit\(row: AdminRow\)[\s\S]*formOpen\.value = true;[\s\S]*scrollToForm\(\);/', $component);
    }

    /**
     * @return array<string, array{resource: string, path: string}>
     */
    public static function adminOperationPageProvider(): array
    {
        return collect(self::adminOperationPages())
            ->mapWithKeys(fn (string $path, string $resource): array => [
                $resource => [
                    'resource' => $resource,
                    'path' => $path,
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, array{resource: string}>
     */
    public static function adminCrudResourceProvider(): array
    {
        return collect([
            'users',
            'roles',
            'permissions',
            'bloggers',
            'courses',
            'lessons',
            'blogs',
            'cms',
            'home_hero_slides',
            'home_page_sections',
            'menus',
            'media',
            'ad_zones',
            'ad_campaigns',
            'ad_creatives',
            'ad_pricing',
            'instructors',
            'payment_products',
            'payment_prices',
            'lead_magnets',
            'discussion_forums',
            'community_groups',
            'settings',
        ])
            ->mapWithKeys(fn (string $resource): array => [
                $resource => ['resource' => $resource],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function adminOperationPages(): array
    {
        return [
            'users' => '/admin/users',
            'roles' => '/admin/roles',
            'permissions' => '/admin/permissions',
            'bloggers' => '/admin/bloggers',
            'courses' => '/admin/courses',
            'lessons' => '/admin/lessons',
            'blogs' => '/admin/blogs',
            'cms' => '/admin/cms',
            'home_hero' => '/admin/cms/home-hero',
            'home_hero_slides' => '/admin/cms/home-hero-slides',
            'home_page_sections' => '/admin/cms/homepage-sections',
            'menus' => '/admin/menus',
            'media' => '/admin/media',
            'ad_zones' => '/admin/ads/zones',
            'ad_campaigns' => '/admin/ads/campaigns',
            'ad_creatives' => '/admin/ads/creatives',
            'advertiser_requests' => '/admin/ads/advertiser-requests',
            'ad_pricing' => '/admin/ads/pricing',
            'instructors' => '/admin/creators/instructors',
            'author_badges' => '/admin/creators/badges',
            'editorial_revisions' => '/admin/editorial/revisions',
            'reviewer_comments' => '/admin/editorial/comments',
            'scheduled_publications' => '/admin/editorial/scheduled',
            'revenue_share_rules' => '/admin/payments/revenue-share',
            'payment_products' => '/admin/payments/products',
            'payment_prices' => '/admin/payments/prices',
            'payment_discounts' => '/admin/growth/discounts',
            'payment_checkouts' => '/admin/payments/checkouts',
            'payment_orders' => '/admin/payments/orders',
            'payment_subscriptions' => '/admin/payments/subscriptions',
            'team_accounts' => '/admin/payments/teams',
            'team_seats' => '/admin/payments/seats',
            'payment_reconciliation' => '/admin/payments/reconciliation',
            'gift_purchases' => '/admin/growth/gifts',
            'affiliate_partners' => '/admin/growth/affiliates',
            'affiliate_visits' => '/admin/growth/affiliate-visits',
            'referral_conversions' => '/admin/growth/referrals',
            'checkout_recoveries' => '/admin/growth/checkout-recoveries',
            'lead_magnets' => '/admin/growth/lead-magnets',
            'newsletter_campaigns' => '/admin/growth/newsletters',
            'lead_submissions' => '/admin/growth/leads',
            'ab_experiments' => '/admin/growth/experiments',
            'ab_variants' => '/admin/growth/variants',
            'social_share_images' => '/admin/growth/social-images',
            'moderation_queue' => '/admin/community/moderation',
            'content_reports' => '/admin/community/reports',
            'blocked_words' => '/admin/community/blocked-words',
            'discussion_forums' => '/admin/community/forums',
            'discussion_threads' => '/admin/community/threads',
            'community_groups' => '/admin/community/groups',
            'settings' => '/admin/settings',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payloadFor(string $resource, string $variant, ?Model $record = null): array
    {
        $token = $resource.'-'.$variant.'-'.str()->random(8);

        return match ($resource) {
            'users' => [
                'name' => 'Smoke User '.$variant,
                'email' => $token.'@example.com',
                'password' => $variant === 'create' ? 'password' : null,
                'status' => UserStatus::Active->value,
                'roles' => [RoleName::Student->value],
            ],
            'roles' => [
                'name' => 'smoke_'.$variant.'_role_'.str()->random(6),
                'permissions' => ['manage_courses', 'manage_lessons'],
            ],
            'permissions' => [
                'name' => 'smoke_'.$variant.'_permission_'.str()->random(6),
            ],
            'bloggers' => [
                'user_id' => $record instanceof BloggerProfile ? $record->user_id : $this->bloggerUser()->id,
                'phone' => '555-0100',
                'expertise' => 'Laravel operations',
                'bio' => 'Smoke-tested blogger profile.',
                'application_reason' => 'Testing approval workflows.',
                'status' => $variant === 'create' ? BloggerStatus::Pending->value : BloggerStatus::Approved->value,
                'admin_notes' => 'Smoke '.$variant,
            ],
            'courses' => [
                'title' => 'Smoke Course '.$variant.' '.str()->headline($token),
                'short_description' => 'Short production training description.',
                'description' => 'Longer production training description for admin smoke coverage.',
                'level' => 'intermediate',
                'language' => 'en',
                'price' => 9900,
                'is_free' => false,
                'ownership_video_url' => 'https://videos.example.com/'.$token.'/ownership',
                'ownership_statement' => 'I confirm this course was created by me and I have rights to publish it on Scratch Learning.',
                'status' => $variant === 'create' ? PublishStatus::Draft->value : PublishStatus::Published->value,
                'seo_title' => 'Smoke Course '.$variant,
                'seo_description' => 'Smoke course SEO description.',
                'admin_notes' => 'Course '.$variant,
            ],
            'lessons' => [
                'course_id' => $record instanceof CourseLesson ? $record->course_id : Course::factory()->create()->id,
                'title' => 'Smoke Lesson '.$variant.' '.str()->headline($token),
                'order_number' => 2,
                'content' => 'Smoke lesson content.',
                'video_type' => VideoType::Url->value,
                'video_url' => 'https://videos.example.com/'.$token,
                'is_free' => false,
                'is_paid' => true,
                'preview_word_limit' => 120,
                'status' => $variant === 'create' ? PublishStatus::Draft->value : PublishStatus::Published->value,
                'seo_title' => 'Smoke Lesson '.$variant,
                'seo_description' => 'Smoke lesson SEO description.',
                'admin_notes' => 'Lesson '.$variant,
            ],
            'blogs' => [
                'title' => 'Smoke Blog '.$variant.' '.str()->headline($token),
                'author_id' => $this->admin->id,
                'excerpt' => 'Smoke blog excerpt.',
                'content' => 'Smoke blog body content.',
                'status' => $variant === 'create' ? PublishStatus::Draft->value : PublishStatus::Approved->value,
                'is_featured' => $variant === 'update',
                'seo_title' => 'Smoke Blog '.$variant,
                'seo_description' => 'Smoke blog SEO description.',
                'admin_notes' => 'Blog '.$variant,
            ],
            'cms' => [
                'title' => 'Smoke Page '.$variant.' '.str()->headline($token),
                'author_id' => $this->admin->id,
                'excerpt' => 'Smoke CMS excerpt.',
                'content' => 'Smoke CMS page content.',
                'template' => 'default',
                'sort_order' => 10,
                'status' => $variant === 'create' ? PublishStatus::Draft->value : PublishStatus::Published->value,
                'seo_title' => 'Smoke Page '.$variant,
                'seo_description' => 'Smoke page SEO description.',
                'admin_notes' => 'Page '.$variant,
            ],
            'home_hero_slides' => [
                'image_url' => 'https://images.example.com/'.$token.'.jpg',
                'image_alt' => 'Smoke hero image',
                'eyebrow' => 'Smoke Hero',
                'title' => 'Smoke Hero Slide '.$variant.' '.str()->headline($token),
                'subtitle' => 'A slide managed from the admin smoke test.',
                'button_label' => 'Open Course',
                'target_url' => '/courses',
                'text_position' => 'left',
                'sort_order' => $variant === 'create' ? 10 : 20,
                'is_active' => true,
                'opens_in_new_tab' => false,
            ],
            'home_page_sections' => [
                'key' => 'smoke-'.$variant.'-'.str()->slug($token),
                'type' => HomePageSectionType::FeaturedCourses->value,
                'eyebrow' => 'Smoke Section',
                'title' => 'Smoke Homepage Section '.$variant,
                'subtitle' => 'Managed section subtitle.',
                'body' => 'Managed section body.',
                'cta_label' => 'View Courses',
                'cta_url' => '/courses',
                'background' => $variant === 'create' ? 'white' : 'soft',
                'sort_order' => $variant === 'create' ? 30 : 40,
                'is_active' => true,
                'payload_content' => '{"source":"deep-smoke"}',
            ],
            'menus' => [
                'name' => 'Smoke Menu '.$variant.' '.str()->headline($token),
                'location' => 'header-'.$variant,
                'is_active' => true,
            ],
            'media' => [
                'uploaded_by' => $this->admin->id,
                'title' => 'Smoke Media '.$variant,
                'path' => 'media/'.$token.'.jpg',
                'url' => '/storage/media/'.$token.'.jpg',
                'alt_text' => 'Smoke media alt',
                'caption' => 'Smoke media caption',
                'mime_type' => 'image/jpeg',
                'size' => 123456,
                'width' => 1600,
                'height' => 900,
                'visibility' => MediaVisibility::Public->value,
            ],
            'ad_zones' => [
                'name' => 'Smoke Ad Zone '.$variant.' '.str()->headline($token),
                'location' => 'smoke.'.$variant,
                'description' => 'Smoke ad zone description.',
                'width' => 728,
                'height' => 90,
                'max_creatives' => 2,
                'status' => AdZoneStatus::Active->value,
            ],
            'ad_campaigns' => [
                'name' => 'Smoke Campaign '.$variant.' '.str()->headline($token),
                'advertiser_name' => 'Smoke Advertiser',
                'advertiser_email' => 'ads-'.$token.'@example.com',
                'status' => AdCampaignStatus::Active->value,
                'pricing_model' => AdPricingModel::Cpm->value,
                'currency' => 'usd',
                'budget_total' => 2500,
                'daily_budget' => 100,
                'cpm_rate' => 20,
                'target_url' => 'https://example.com/'.$token,
            ],
            'ad_creatives' => [
                'ad_campaign_id' => $record instanceof AdCreative ? $record->ad_campaign_id : AdCampaign::factory()->active()->create()->id,
                'name' => 'Smoke Creative '.$variant.' '.str()->headline($token),
                'type' => AdCreativeType::Text->value,
                'status' => AdCreativeStatus::Active->value,
                'headline' => 'Smoke creative headline',
                'body' => 'Smoke creative body.',
                'cta_text' => 'Learn more',
                'target_url' => 'https://example.com/'.$token,
                'weight' => 100,
            ],
            'ad_pricing' => [
                'ad_zone_id' => $record instanceof AdPricingSetting ? $record->ad_zone_id : AdZone::factory()->active()->create()->id,
                'name' => 'Smoke Pricing '.$variant.' '.str()->headline($token),
                'pricing_model' => AdPricingModel::Cpm->value,
                'currency' => 'usd',
                'cpm_rate' => 25,
                'cpc_rate' => 2,
                'flat_rate' => 500,
                'min_spend' => 100,
                'is_active' => true,
                'notes' => 'Smoke pricing notes.',
            ],
            'instructors' => [
                'user_id' => $record instanceof InstructorProfile ? $record->user_id : User::factory()->create()->id,
                'display_name' => 'Smoke Instructor '.$variant.' '.str()->headline($token),
                'headline' => 'Industrial Laravel instructor',
                'bio' => 'Smoke-tested instructor profile.',
                'credentials' => 'Platform engineering',
                'expertise' => 'Laravel',
                'website_url' => 'https://example.com/'.$token,
                'status' => $variant === 'create' ? InstructorProfileStatus::Pending->value : InstructorProfileStatus::Approved->value,
                'is_verified_expert' => $variant === 'update',
                'accepts_revenue_share' => true,
                'payout_currency' => 'usd',
                'admin_notes' => 'Instructor '.$variant,
                'metadata_content' => '{"source":"deep-smoke"}',
            ],
            'payment_products' => [
                'course_id' => $record instanceof PaymentProduct ? $record->course_id : Course::factory()->create()->id,
                'name' => 'Smoke Product '.$variant.' '.str()->headline($token),
                'description' => 'Smoke payment product.',
                'type' => PaymentProductType::Course->value,
                'status' => PaymentProductStatus::Active->value,
                'paddle_product_id' => 'pro_'.$token,
                'tax_category' => 'training-services',
                'metadata_content' => '{"source":"deep-smoke"}',
            ],
            'payment_prices' => [
                'payment_product_id' => $record instanceof PaymentPrice ? $record->payment_product_id : PaymentProduct::factory()->create()->id,
                'name' => 'Smoke Plan '.$variant.' '.str()->headline($token),
                'paddle_price_id' => 'pri_'.$token,
                'billing_interval' => PaymentBillingInterval::Month->value,
                'is_recurring' => true,
                'currency' => 'usd',
                'amount' => 2900,
                'trial_days' => 7,
                'seat_min' => 1,
                'seat_max' => 10,
                'is_active' => true,
                'metadata_content' => '{"source":"deep-smoke"}',
            ],
            'lead_magnets' => [
                'title' => 'Smoke Lead Magnet '.$variant.' '.str()->headline($token),
                'slug' => 'smoke-lead-'.$variant.'-'.str()->slug($token),
                'description' => 'Smoke lead magnet.',
                'status' => GrowthStatus::Active->value,
                'form_headline' => 'Get the smoke checklist',
                'delivery_url' => '/downloads/'.$token,
                'metadata_content' => '{"source":"deep-smoke"}',
            ],
            'discussion_forums' => [
                'course_id' => $record instanceof DiscussionForum ? $record->course_id : Course::factory()->create()->id,
                'created_by' => $this->admin->id,
                'title' => 'Smoke Forum '.$variant.' '.str()->headline($token),
                'description' => 'Smoke discussion forum.',
                'visibility' => CommunityVisibility::Members->value,
                'status' => CommunityContentStatus::Approved->value,
                'sort_order' => 1,
            ],
            'community_groups' => [
                'course_id' => $record instanceof CommunityGroup ? $record->course_id : Course::factory()->create()->id,
                'created_by' => $this->admin->id,
                'name' => 'Smoke Group '.$variant.' '.str()->headline($token),
                'description' => 'Smoke community group.',
                'visibility' => CommunityVisibility::PaidMembers->value,
                'status' => CommunityContentStatus::Approved->value,
                'requires_paid_access' => true,
            ],
            'settings' => [
                'group' => 'smoke',
                'key' => 'smoke.'.$variant.'.'.$token,
                'value_content' => '{"enabled":true}',
                'is_encrypted' => false,
            ],
            default => throw new \InvalidArgumentException("Unsupported CRUD smoke resource [{$resource}]."),
        };
    }

    /**
     * @return class-string<Model>
     */
    private function modelClassFor(string $resource): string
    {
        return match ($resource) {
            'users' => User::class,
            'roles' => Role::class,
            'permissions' => Permission::class,
            'bloggers' => BloggerProfile::class,
            'courses' => Course::class,
            'lessons' => CourseLesson::class,
            'blogs' => BlogPost::class,
            'cms' => Page::class,
            'home_hero_slides' => HomeHeroSlide::class,
            'home_page_sections' => HomePageSection::class,
            'menus' => Menu::class,
            'media' => MediaAsset::class,
            'ad_zones' => AdZone::class,
            'ad_campaigns' => AdCampaign::class,
            'ad_creatives' => AdCreative::class,
            'ad_pricing' => AdPricingSetting::class,
            'instructors' => InstructorProfile::class,
            'payment_products' => PaymentProduct::class,
            'payment_prices' => PaymentPrice::class,
            'lead_magnets' => LeadMagnet::class,
            'discussion_forums' => DiscussionForum::class,
            'community_groups' => CommunityGroup::class,
            'settings' => SiteSetting::class,
            default => throw new \InvalidArgumentException("Unsupported model resource [{$resource}]."),
        };
    }

    private function lookupColumnFor(string $resource): string
    {
        return match ($resource) {
            'users' => 'email',
            'bloggers' => 'user_id',
            'media' => 'path',
            'home_page_sections', 'settings' => 'key',
            'courses',
            'lessons',
            'blogs',
            'cms',
            'home_hero_slides',
            'lead_magnets',
            'discussion_forums' => 'title',
            'instructors' => 'display_name',
            default => 'name',
        };
    }

    private function bloggerUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        return $user;
    }

    private function attachCourseCurriculum(Course $course): void
    {
        $section = CourseSection::factory()->create([
            'course_id' => $course->id,
        ]);

        CourseLesson::factory()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function inertiaProps($response): array
    {
        $page = $response->viewData('page');

        $this->assertIsArray($page);
        $this->assertArrayHasKey('props', $page);

        return $page['props'];
    }
}
