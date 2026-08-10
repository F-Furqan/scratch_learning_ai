<?php

namespace Tests\Feature;

use App\Enums\PublishStatus;
use App\Enums\RoleName;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BlogRichContentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole(RoleName::SuperAdmin->value);
    }

    public function test_blog_form_exposes_multiple_tags_rich_content_and_a_clear_excerpt_label(): void
    {
        BlogTag::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get(route('admin.blogs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/Operations')
                ->where('fields.4.key', 'blog_tag_ids')
                ->where('fields.4.type', 'multiselect')
                ->has('fields.4.options', 3)
                ->where('fields.5.label', 'Excerpt (Short Summary)')
                ->where('fields.6.key', 'content')
                ->where('fields.6.type', 'richtext'));
    }

    public function test_admin_can_save_multiple_tags_and_safe_code_blocks_for_public_rendering(): void
    {
        $tags = BlogTag::factory()->count(3)->create();
        $content = '<h2>Service example</h2><p>Use this implementation:</p>'
            .'<pre><code class="language-php">&lt;?php echo "ready";</code></pre>'
            .'<script>alert("unsafe")</script>';

        $this->actingAs($this->admin)
            ->from(route('admin.blogs.index'))
            ->post(route('admin.blogs.store'), [
                'title' => 'Rich code article',
                'author_id' => $this->admin->id,
                'excerpt' => 'A short summary used on blog cards and article headers.',
                'content' => $content,
                'status' => PublishStatus::Draft->value,
                'is_featured' => false,
                'blog_tag_ids' => [$tags[0]->id, $tags[1]->id],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.blogs.index'));

        $post = BlogPost::query()->where('title', 'Rich code article')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$tags[0]->id, $tags[1]->id],
            $post->tags()->pluck('blog_tags.id')->all(),
        );
        $this->assertStringContainsString('<pre><code class="language-php">', (string) $post->content);
        $this->assertStringNotContainsString('<script', (string) $post->content);

        $post->forceFill([
            'status' => PublishStatus::Published,
            'published_at' => now(),
        ])->save();

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->has('post.tags', 2)
                ->where('post.content', fn (mixed $value): bool => is_string($value)
                    && str_contains($value, '<pre><code class="language-php">')
                    && ! str_contains($value, '<script')));
    }
}
