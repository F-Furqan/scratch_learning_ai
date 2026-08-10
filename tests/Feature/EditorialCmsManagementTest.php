<?php

namespace Tests\Feature;

use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlogCategory;
use App\Models\BlogFaq;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\ContentBlock;
use App\Models\CreatorAgreementAcceptance;
use App\Models\EditorialRevision;
use App\Models\MediaAsset;
use App\Models\MediaUsage;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\ReviewerComment;
use App\Models\User;
use App\Support\PublicSite\PublicContentPresenter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Admin\Registry\AdminResourceRegistry;
use Tests\TestCase;

class EditorialCmsManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_phase_four_resources_are_registered_and_visible_to_admin(): void
    {
        $resources = [
            'blog_categories',
            'blog_tags',
            'blog_faqs',
            'blog_comments',
            'content_blocks',
            'menu_items',
            'media_folders',
            'media_usages',
            'creator_agreements',
        ];

        $this->assertCount(104, app(AdminResourceRegistry::class)->all());

        foreach ($resources as $resource) {
            $definition = app(AdminResourceRegistry::class)->get($resource);
            $this->actingAs($this->admin)
                ->get(route('admin.'.$definition->routeName.'.index'))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page->where('resource', $resource));
        }
    }

    public function test_blog_taxonomies_have_unique_slugs_and_categories_delete_safely(): void
    {
        foreach (['Platform Engineering', 'Platform Engineering'] as $name) {
            $this->actingAs($this->admin)->post(route('admin.blog-categories.store'), [
                'name' => $name,
                'description' => 'Editorial category',
                'is_active' => true,
                'schema' => '',
            ])->assertRedirect();

            $this->actingAs($this->admin)->post(route('admin.blog-tags.store'), [
                'name' => $name,
            ])->assertRedirect();
        }

        $this->assertEqualsCanonicalizing(
            ['platform-engineering', 'platform-engineering-2'],
            BlogCategory::query()->pluck('slug')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['platform-engineering', 'platform-engineering-2'],
            BlogTag::query()->pluck('slug')->all(),
        );

        $category = BlogCategory::query()->firstOrFail();
        BlogPost::factory()->create(['blog_category_id' => $category->id]);

        $this->actingAs($this->admin)
            ->delete(route('admin.blog-categories.destroy', $category))
            ->assertSessionHasErrors('delete');
        $this->assertDatabaseHas('blog_categories', ['id' => $category->id]);
    }

    public function test_rich_faqs_and_content_blocks_are_sanitized_and_reorderable(): void
    {
        $post = BlogPost::factory()->create();
        $page = Page::factory()->create();
        $unsafe = '<h2>Example</h2><pre><code class="language-php">echo "ok";</code></pre><script>alert(1)</script>';

        $this->actingAs($this->admin)->post(route('admin.blog-faqs.store'), [
            'blog_post_id' => $post->id,
            'question' => 'How does this work?',
            'answer' => $unsafe,
            'sort_order' => 4,
            'status' => PublishStatus::Published->value,
        ])->assertRedirect();

        $this->actingAs($this->admin)->post(route('admin.cms.content-blocks.store'), [
            'page_id' => $page->id,
            'key' => 'Code Example',
            'title' => 'Code Example',
            'body' => $unsafe,
            'settings_content' => '{"width":"contained"}',
            'sort_order' => 7,
            'is_active' => true,
        ])->assertRedirect();

        $faq = BlogFaq::query()->firstOrFail();
        $block = ContentBlock::query()->firstOrFail();
        $this->assertStringContainsString('language-php', (string) $faq->answer);
        $this->assertStringNotContainsString('<script', (string) $faq->answer);
        $this->assertStringContainsString('<pre>', (string) $block->body);
        $this->assertStringNotContainsString('alert(1)', (string) $block->body);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.blog-faqs.reorder'), [
                'items' => [['id' => $faq->id, 'sort_order' => 0]],
            ])
            ->assertOk();
        $this->assertSame(0, $faq->fresh()->sort_order);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.blog_faqs.reordered']);
    }

    public function test_menu_builder_persists_nested_order_and_rejects_cycles(): void
    {
        $menu = Menu::factory()->create();
        $parent = MenuItem::factory()->for($menu)->create(['sort_order' => 0]);
        $child = MenuItem::factory()->for($menu)->create(['sort_order' => 1]);

        $this->actingAs($this->admin)
            ->get(route('admin.menus.builder.show', $menu))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/editorial/MenuBuilder')
                ->has('items', 2));

        $this->actingAs($this->admin)
            ->patchJson(route('admin.menus.builder.reorder', $menu), [
                'items' => [
                    ['id' => $parent->id, 'parent_id' => null, 'sort_order' => 0],
                    ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 0],
                ],
            ])
            ->assertOk();
        $this->assertSame($parent->id, $child->fresh()->parent_id);

        $this->actingAs($this->admin)
            ->patchJson(route('admin.menus.builder.reorder', $menu), [
                'items' => [
                    ['id' => $parent->id, 'parent_id' => $child->id, 'sort_order' => 0],
                    ['id' => $child->id, 'parent_id' => $parent->id, 'sort_order' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');
    }

    public function test_media_assignments_create_usage_references_and_prevent_unsafe_deletion(): void
    {
        $asset = MediaAsset::factory()->create();
        $post = BlogPost::factory()->create([
            'author_id' => $this->admin->id,
            'status' => PublishStatus::Draft,
        ]);

        $this->actingAs($this->admin)->patch(route('admin.blogs.update', $post), [
            'title' => $post->title,
            'author_id' => $this->admin->id,
            'featured_image_media_id' => $asset->id,
            'excerpt' => $post->excerpt,
            'content' => '<p>Safe content</p>',
            'status' => PublishStatus::Draft->value,
            'is_featured' => false,
            'blog_tag_ids' => [],
        ])->assertRedirect();

        $this->assertDatabaseHas('media_usages', [
            'media_asset_id' => $asset->id,
            'mediable_type' => $post->getMorphClass(),
            'mediable_id' => $post->id,
            'collection' => 'featured_image',
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.media.destroy', $asset))
            ->assertUnprocessable();
        $this->assertDatabaseHas('media_assets', ['id' => $asset->id]);
    }

    public function test_agreement_acceptance_and_media_usage_histories_are_read_only(): void
    {
        $acceptance = CreatorAgreementAcceptance::factory()->create();
        $usage = MediaUsage::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.creators.agreements.store'), [])
            ->assertStatus(405);
        $this->actingAs($this->admin)
            ->delete(route('admin.media.usages.destroy', $usage))
            ->assertStatus(405);

        $this->assertDatabaseHas('creator_agreement_acceptances', ['id' => $acceptance->id]);
        $this->assertDatabaseHas('media_usages', ['id' => $usage->id]);
    }

    public function test_revision_comparison_supports_field_comments_and_resolution(): void
    {
        $post = BlogPost::factory()->create(['title' => 'Current title', 'content' => '<p>Current</p>']);
        $revision = EditorialRevision::factory()->submitted()->create([
            'editorialable_type' => $post->getMorphClass(),
            'editorialable_id' => $post->id,
            'payload' => ['title' => 'Proposed title', 'content' => '<p>Proposed</p>'],
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.editorial.revisions.review', $revision))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/editorial/RevisionReview')
                ->where('revision.status', EditorialRevisionStatus::Submitted->value)
                ->where('fields.0.changed', true));

        $this->actingAs($this->admin)
            ->post(route('admin.editorial.revisions.comments.store', $revision), [
                'field_path' => 'title',
                'body' => 'Please make the title more specific.',
            ])
            ->assertRedirect();

        $comment = ReviewerComment::query()->firstOrFail();
        $this->actingAs($this->admin)
            ->patch(route('admin.editorial.revisions.comments.resolve', [$revision, $comment]))
            ->assertRedirect();
        $this->assertTrue($comment->fresh()->is_resolved);
        $this->assertSame($this->admin->id, $comment->fresh()->resolved_by);
    }

    public function test_public_presenter_preserves_safe_rich_editor_markup(): void
    {
        $post = BlogPost::factory()->create([
            'content' => '<h2>Code</h2><pre><code class="language-js">const ready = true;</code></pre><script>bad()</script>',
        ]);
        $page = Page::factory()->create(['content' => '<p><strong>Useful</strong> page</p>']);
        $page->blocks()->create([
            'key' => 'example',
            'body' => '<pre><code class="language-php">echo 1;</code></pre>',
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $presenter = app(PublicContentPresenter::class);
        $blogPayload = $presenter->blogDetail($post->load(['category', 'author', 'featuredImage', 'tags', 'faqs']));
        $pagePayload = $presenter->page($page->load('blocks'));

        $this->assertStringContainsString('language-js', (string) $blogPayload['content']);
        $this->assertStringNotContainsString('<script', (string) $blogPayload['content']);
        $this->assertStringContainsString('<strong>Useful</strong>', (string) $pagePayload['content']);
        $this->assertStringContainsString('language-php', (string) $pagePayload['blocks'][0]['body']);
    }
}
