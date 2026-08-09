<?php

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\AdZone;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Page;
use App\Models\PaymentWebhookEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Tests\TestCase;

class Phase7HardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_roles_and_permissions_gate_admin_ad_operations(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole(RoleName::SubAdmin->value);

        $payload = [
            'name' => 'Homepage Leaderboard',
            'location' => 'home.hero',
            'description' => 'Launch placement',
            'width' => 970,
            'height' => 250,
            'max_creatives' => 3,
            'status' => 'active',
        ];

        $this->actingAs($subAdmin)
            ->post(route('admin.ads.zones.store'), $payload)
            ->assertForbidden();

        $subAdmin->givePermissionTo(Permission::findByName(PermissionName::ManageAds->value, 'web'));

        $this->actingAs($subAdmin)
            ->post(route('admin.ads.zones.store'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas(AdZone::class, [
            'name' => 'Homepage Leaderboard',
            'location' => 'home.hero',
        ]);
    }

    public function test_authorization_blocks_reports_without_view_reports_permission(): void
    {
        $subAdmin = User::factory()->create();
        $subAdmin->assignRole(RoleName::SubAdmin->value);
        $subAdmin->givePermissionTo(Permission::findByName(PermissionName::ManageAds->value, 'web'));

        $this->actingAs($subAdmin)
            ->get(route('admin.reports.index'))
            ->assertForbidden();

        $subAdmin->givePermissionTo(Permission::findByName(PermissionName::ViewReports->value, 'web'));

        $this->actingAs($subAdmin)
            ->get(route('admin.reports.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/Reports'));
    }

    public function test_future_published_content_stays_private_until_publish_time(): void
    {
        $course = Course::factory()->create([
            'title' => 'Future Launch Course',
            'status' => PublishStatus::Published,
            'published_at' => now()->addDay(),
        ]);

        $this->get(route('public.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/courses/Index')
                ->where('courses.total', 0),
            );

        $this->get(route('public.courses.show', $course->slug))->assertNotFound();
        $this->getJson(route('api.v1.courses.show', $course->slug))->assertNotFound();
    }

    public function test_public_course_api_contract_caps_pagination_and_shape(): void
    {
        Course::factory()->published()->count(55)->create();

        $this->getJson(route('api.v1.courses.index', ['per_page' => 999]))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'short_description',
                        'price',
                        'is_free',
                        'status',
                        'published_at',
                        'seo',
                    ],
                ],
                'links',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ])
            ->assertJsonPath('meta.per_page', 50)
            ->assertJsonCount(50, 'data');
    }

    public function test_public_api_rate_limit_is_enforced(): void
    {
        Course::factory()->published()->create();

        for ($attempt = 1; $attempt <= 120; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
                ->getJson(route('api.v1.courses.index'))
                ->assertOk();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->getJson(route('api.v1.courses.index'))
            ->assertStatus(HttpResponse::HTTP_TOO_MANY_REQUESTS);
    }

    public function test_paddle_webhook_accepts_valid_signature_and_is_idempotent(): void
    {
        Config::set('payments.paddle.webhook_secret', 'test_webhook_secret');
        Config::set('payments.paddle.webhook_tolerance_seconds', 300);

        $payload = json_encode([
            'event_id' => 'evt_01phase7',
            'event_type' => 'transaction.completed',
            'occurred_at' => now()->toISOString(),
            'data' => [
                'id' => 'txn_01phase7',
                'status' => 'completed',
            ],
        ], JSON_THROW_ON_ERROR);

        $signature = $this->paddleSignature($payload, 'test_webhook_secret');

        $this->postRawPaddleWebhook($payload, $signature)
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.duplicate', false);

        $this->postRawPaddleWebhook($payload, $signature)
            ->assertOk()
            ->assertJsonPath('data.accepted', true)
            ->assertJsonPath('data.duplicate', true);

        $this->assertDatabaseCount(PaymentWebhookEvent::class, 1);
        $this->assertDatabaseHas(PaymentWebhookEvent::class, [
            'provider' => 'paddle',
            'event_id' => 'evt_01phase7',
            'event_type' => 'transaction.completed',
            'status' => 'processed',
        ]);
    }

    public function test_paddle_webhook_rejects_invalid_and_replayed_signatures(): void
    {
        Config::set('payments.paddle.webhook_secret', 'test_webhook_secret');
        Config::set('payments.paddle.webhook_tolerance_seconds', 5);

        $payload = json_encode([
            'event_id' => 'evt_rejected',
            'event_type' => 'subscription.created',
            'data' => [],
        ], JSON_THROW_ON_ERROR);

        $this->postRawPaddleWebhook($payload, 'ts='.now()->timestamp.';h1=bad-signature')
            ->assertStatus(HttpResponse::HTTP_UNAUTHORIZED);

        $oldTimestamp = now()->subMinute()->timestamp;
        $oldSignature = hash_hmac('sha256', $oldTimestamp.':'.$payload, 'test_webhook_secret');

        $this->postRawPaddleWebhook($payload, "ts={$oldTimestamp};h1={$oldSignature}")
            ->assertStatus(HttpResponse::HTTP_UNAUTHORIZED);

        $this->assertDatabaseCount(PaymentWebhookEvent::class, 0);
    }

    public function test_public_payloads_are_sanitized_before_rendering_or_api_output(): void
    {
        $post = BlogPost::factory()->published()->create([
            'title' => 'Security Note',
            'excerpt' => 'Safe excerpt <svg onload=alert(1)>bad</svg>',
            'content' => 'Safe copy<script>alert("xss")</script><img src=x onerror=alert(1)>',
        ]);

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('post.excerpt', 'Safe excerpt')
                ->where('post.content', 'Safe copy'),
            );

        $content = (string) $this->getJson(route('api.v1.blogs.show', $post->slug))
            ->assertOk()
            ->json('data.content');

        $this->assertStringContainsString('Safe copy', $content);
        $this->assertStringNotContainsString('<script', $content);
        $this->assertStringNotContainsString('onerror', $content);
        $this->assertStringNotContainsString('javascript:', $content);
    }

    public function test_browser_smoke_public_critical_paths_render(): void
    {
        $course = Course::factory()->free()->published()->create(['title' => 'Smoke Course']);
        $lesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'title' => 'Smoke Lesson',
        ]);
        $post = BlogPost::factory()->published()->create(['title' => 'Smoke Blog']);
        $page = Page::factory()->published()->create(['title' => 'Smoke Page']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/Home'));

        $this->get(route('public.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/courses/Index'));

        $this->get(route('public.courses.show', $course->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/courses/Show'));

        $this->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/lessons/Show'));

        $this->get(route('public.blog.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/blog/Index'));

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/blog/Show'));

        $this->get(route('public.pages.show', $page->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('public/pages/Show'));
    }

    public function test_load_checks_for_public_listing_pages_and_admin_tables(): void
    {
        Course::factory()->published()->count(80)->create();
        BlogPost::factory()->published()->count(80)->create();
        User::factory()->count(80)->create();

        $admin = User::factory()->create();
        $admin->assignRole(RoleName::SuperAdmin->value);

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $start = hrtime(true);

        $this->get(route('public.courses.index'))->assertOk();
        $this->get(route('public.blog.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        $this->assertLessThan((float) config('operations.monitoring.public_listing_max_ms', 1500), $elapsedMs);
        $this->assertLessThan(120, $queries);
    }

    private function paddleSignature(string $payload, string $secret): string
    {
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.':'.$payload, $secret);

        return "ts={$timestamp};h1={$signature}";
    }

    private function postRawPaddleWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', route('api.webhooks.paddle'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_PADDLE_SIGNATURE' => $signature,
            'REMOTE_ADDR' => '198.51.100.42',
        ], $payload);
    }
}
