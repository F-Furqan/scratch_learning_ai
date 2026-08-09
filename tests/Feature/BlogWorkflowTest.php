<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\EditorialRevisionStatus;
use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlogCategory;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CreatorAgreementAcceptance;
use App\Models\EditorialRevision;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlogWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_submitted_blog_is_not_public_until_admin_approves_and_publishes(): void
    {
        $admin = $this->admin();
        $post = BlogPost::factory()->submitted()->create([
            'title' => 'Submitted Creator Blog',
            'content' => 'Waiting for review.',
        ]);

        $this->get(route('public.blog.show', $post->slug))->assertNotFound();

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->post(route('admin.blog-workflow.blogs.approve', $post), [
                'note' => 'Ready to publish.',
            ])
            ->assertRedirect('/admin/blogs');

        $this->assertSame(PublishStatus::Approved, $post->fresh()->status);
        $this->get(route('public.blog.show', $post->slug))->assertNotFound();

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->post(route('admin.blog-workflow.blogs.publish', $post), [
                'note' => 'Publishing approved article.',
            ])
            ->assertRedirect('/admin/blogs');

        $post->refresh();

        $this->assertSame(PublishStatus::Published, $post->status);
        $this->assertNotNull($post->published_at);

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('post.title', 'Submitted Creator Blog'),
            );
    }

    public function test_published_blog_edits_create_revision_without_changing_live_post(): void
    {
        $creator = $this->approvedCreator();
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'title' => 'Live Creator Article',
            'content' => 'Original live body.',
        ]);

        $this->actingAs($creator)
            ->patch(route('creator.blogs.update', $post), [
                'blog_category_id' => $category->id,
                'title' => 'Revised Creator Article',
                'excerpt' => 'Revised excerpt.',
                'content' => 'Revision body waiting on admin.',
                'seo_title' => 'Revised SEO',
                'seo_description' => 'Revised SEO description.',
                'copyright_declaration_accepted' => '1',
            ])
            ->assertRedirect(route('creator.blogs.index', ['status' => PublishStatus::Published->value], false));

        $post->refresh();

        $this->assertSame('Live Creator Article', $post->title);
        $this->assertSame('Original live body.', $post->content);
        $this->assertSame(PublishStatus::Published, $post->status);

        $revision = EditorialRevision::query()->where('editorialable_id', $post->id)->firstOrFail();

        $this->assertSame(EditorialRevisionStatus::Submitted, $revision->status);
        $this->assertSame('Revised Creator Article', $revision->title);
        $this->assertSame('Revision body waiting on admin.', $revision->payload['content']);

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('post.title', 'Live Creator Article')
                ->where('post.content', 'Original live body.'),
            );
    }

    public function test_admin_approval_applies_published_blog_revision(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $category = BlogCategory::factory()->create();
        $post = BlogPost::factory()->published()->create([
            'author_id' => $creator->id,
            'title' => 'Original Workflow Article',
            'content' => 'Original workflow body.',
        ]);
        $revision = $post->editorialRevisions()->create([
            'author_id' => $creator->id,
            'title' => 'Approved Workflow Article',
            'summary' => 'Updated summary.',
            'payload' => [
                'blog_category_id' => $category->id,
                'title' => 'Approved Workflow Article',
                'excerpt' => 'Updated summary.',
                'content' => 'Approved revised body.',
                'seo_title' => 'Approved SEO',
                'seo_description' => 'Approved SEO description.',
            ],
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from('/admin/editorial/revisions')
            ->post(route('admin.blog-workflow.revisions.approve', $revision), [
                'note' => 'Revision approved.',
            ])
            ->assertRedirect('/admin/editorial/revisions');

        $post->refresh();
        $revision->refresh();

        $this->assertSame(PublishStatus::Published, $post->status);
        $this->assertSame('Approved Workflow Article', $post->title);
        $this->assertSame('Approved revised body.', $post->content);
        $this->assertSame(EditorialRevisionStatus::Approved, $revision->status);

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('post.title', 'Approved Workflow Article')
                ->where('post.content', 'Approved revised body.'),
            );
    }

    public function test_generic_admin_cannot_publish_blog_before_approval(): void
    {
        $admin = $this->admin();
        $post = BlogPost::factory()->submitted()->create();

        $this->actingAs($admin)
            ->from('/admin/blogs')
            ->patch('/admin/blogs/'.$post->id, [
                'title' => $post->title,
                'author_id' => $post->author_id,
                'excerpt' => $post->excerpt,
                'content' => $post->content,
                'status' => PublishStatus::Published->value,
                'is_featured' => false,
            ])
            ->assertStatus(422);

        $this->assertSame(PublishStatus::Submitted, $post->fresh()->status);
    }

    public function test_admin_tables_expose_blog_workflow_actions(): void
    {
        $admin = $this->admin();
        $creator = $this->approvedCreator();
        $post = BlogPost::factory()->submitted()->create([
            'author_id' => $creator->id,
        ]);
        $revision = $post->editorialRevisions()->create([
            'author_id' => $creator->id,
            'title' => 'Workflow action revision',
            'payload' => [
                'title' => 'Workflow action revision',
                'content' => 'Revision body.',
            ],
            'status' => EditorialRevisionStatus::Submitted,
            'submitted_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/blogs?status='.PublishStatus::Submitted->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $post->id)
                ->where('rows.data.0.workflow_actions.0.label', 'Approve')
                ->where('rows.data.0.workflow_actions.1.label', 'Changes')
                ->where('rows.data.0.workflow_actions.2.label', 'Reject'),
            );

        $this->actingAs($admin)
            ->get('/admin/editorial/revisions?status='.EditorialRevisionStatus::Submitted->value)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('rows.data.0.id', $revision->id)
                ->where('rows.data.0.workflow_actions.0.label', 'Apply')
                ->where('rows.data.0.workflow_actions.1.label', 'Changes')
                ->where('rows.data.0.workflow_actions.2.label', 'Reject'),
            );
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    private function approvedCreator(): User
    {
        Role::findOrCreate(RoleName::Blogger->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        BloggerProfile::factory()->create([
            'user_id' => $user->id,
            'status' => BloggerStatus::Approved,
        ]);

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        return $user;
    }
}
