<?php

namespace Tests\Feature;

use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseFaq;
use App\Models\CourseLesson;
use App\Models\CourseSection;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicWebsiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_replaces_starter_welcome_page(): void
    {
        Course::factory()->published()->create(['title' => 'Industrial Laravel']);
        BlogPost::factory()->published()->create(['title' => 'Editorial Operations']);
        CourseFaq::factory()->published()->create(['question' => 'Is this production ready?']);

        $this->get(route('home'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/Home')
                ->has('courses', 1)
                ->has('posts', 1)
                ->where('seo.structured_data.0.@type', 'Organization'),
            );
    }

    public function test_course_listing_only_exposes_published_courses(): void
    {
        $published = Course::factory()->published()->create(['title' => 'Visible Course']);

        Course::factory()->create(['title' => 'Hidden Draft Course']);

        $this->get(route('public.courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/courses/Index')
                ->has('courses.data', 1)
                ->where('courses.data.0.slug', $published->slug),
            );
    }

    public function test_course_detail_renders_curriculum_and_structured_data(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Operations Course']);
        $section = CourseSection::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Foundation',
        ]);

        CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'course_section_id' => $section->id,
            'title' => 'First Lesson',
        ]);

        CourseFaq::factory()->published()->create([
            'course_id' => $course->id,
            'question' => 'Does it include FAQs?',
        ]);

        $this->get(route('public.courses.show', $course->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/courses/Show')
                ->where('course.slug', $course->slug)
                ->has('course.sections', 1)
                ->has('course.sections.0.lessons', 1)
                ->where('seo.structured_data.1.@type', 'Course')
                ->where('seo.structured_data.2.@type', 'FAQPage'),
            );
    }

    public function test_paid_lesson_is_locked_and_does_not_expose_full_content(): void
    {
        $course = Course::factory()->published()->create([
            'is_free' => false,
            'price' => 199,
        ]);

        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Locked Lesson',
            'content' => 'alpha beta gamma delta epsilon zeta',
            'is_free' => false,
            'is_paid' => true,
            'preview_word_limit' => 3,
        ]);

        $this->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/lessons/Show')
                ->where('lesson.is_locked', true)
                ->where('lesson.content', null)
                ->where('lesson.preview', 'alpha beta gamma...'),
            );
    }

    public function test_free_lesson_exposes_content(): void
    {
        $course = Course::factory()->free()->published()->create();
        $lesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'content' => 'This lesson is open to public readers.',
        ]);

        $this->get(route('public.lessons.show', [$course->slug, $lesson->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/lessons/Show')
                ->where('lesson.is_locked', false)
                ->where('lesson.content', 'This lesson is open to public readers.'),
            );
    }

    public function test_blog_detail_renders_article_schema(): void
    {
        $post = BlogPost::factory()->published()->create(['title' => 'Public Article']);

        $this->get(route('public.blog.show', $post->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/blog/Show')
                ->where('post.slug', $post->slug)
                ->where('seo.structured_data.1.@type', 'Article'),
            );
    }

    public function test_blogger_profiles_only_show_approved_profiles(): void
    {
        $approvedUser = User::factory()->create(['name' => 'Approved Writer']);
        $pendingUser = User::factory()->create(['name' => 'Pending Writer']);

        BloggerProfile::factory()->approved()->create(['user_id' => $approvedUser->id]);
        BloggerProfile::factory()->create(['user_id' => $pendingUser->id]);

        $this->get(route('public.bloggers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/bloggers/Index')
                ->has('bloggers.data', 1)
                ->where('bloggers.data.0.name', 'Approved Writer'),
            );
    }

    public function test_cms_page_renders_publicly_when_published(): void
    {
        $page = Page::factory()->published()->create([
            'title' => 'About Scratch Learning',
            'content' => 'Public CMS body.',
        ]);

        $this->get(route('public.pages.show', $page->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/pages/Show')
                ->where('page.title', 'About Scratch Learning')
                ->where('page.content', 'Public CMS body.')
                ->where('seo.structured_data.1.@type', 'WebPage'),
            );
    }

    public function test_sitemap_includes_published_content_and_excludes_drafts(): void
    {
        $publishedCourse = Course::factory()->published()->create();
        $draftCourse = Course::factory()->create();
        $publishedPost = BlogPost::factory()->published()->create();
        $publishedPage = Page::factory()->published()->create();

        $this->get(route('sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee(route('public.courses.show', $publishedCourse->slug), false)
            ->assertSee(route('public.blog.show', $publishedPost->slug), false)
            ->assertSee(route('public.pages.show', $publishedPage->slug), false)
            ->assertDontSee(route('public.courses.show', $draftCourse->slug), false);
    }

    public function test_robots_points_to_sitemap_and_blocks_private_areas(): void
    {
        $this->get(route('robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Disallow: /admin', false)
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }
}
