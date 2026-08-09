<?php

namespace Tests\Feature;

use App\Models\BlogPost;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseLesson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentDataModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_slugs_are_generated_with_uniqueness_guarantees(): void
    {
        $first = CourseCategory::factory()->create(['name' => 'Laravel']);
        $second = CourseCategory::factory()->create(['name' => 'Laravel']);

        $this->assertSame('laravel', $first->slug);
        $this->assertSame('laravel-2', $second->slug);
    }

    public function test_public_course_api_only_returns_published_courses(): void
    {
        $publishedCourse = Course::factory()->published()->create([
            'title' => 'Visible Industrial Course',
        ]);

        $draftCourse = Course::factory()->create([
            'title' => 'Hidden Draft Course',
        ]);

        $this->getJson(route('api.v1.courses.index'))
            ->assertOk()
            ->assertJsonFragment(['slug' => $publishedCourse->slug])
            ->assertJsonMissing(['slug' => $draftCourse->slug]);
    }

    public function test_public_blog_api_only_returns_published_posts(): void
    {
        $publishedPost = BlogPost::factory()->published()->create([
            'title' => 'Visible Engineering Post',
        ]);

        $draftPost = BlogPost::factory()->create([
            'title' => 'Hidden Draft Post',
        ]);

        $this->getJson(route('api.v1.blogs.index'))
            ->assertOk()
            ->assertJsonFragment(['slug' => $publishedPost->slug])
            ->assertJsonMissing(['slug' => $draftPost->slug]);
    }

    public function test_course_lesson_api_uses_course_scoped_slug_binding(): void
    {
        $course = Course::factory()->published()->create();
        $otherCourse = Course::factory()->published()->create();

        $lesson = CourseLesson::factory()->free()->published()->create([
            'course_id' => $course->id,
            'title' => 'Shared Lesson Title',
            'content' => 'This free lesson content can be shown publicly.',
        ]);

        CourseLesson::factory()->free()->published()->create([
            'course_id' => $otherCourse->id,
            'title' => 'Shared Lesson Title',
        ]);

        $this->getJson(route('api.v1.courses.lessons.show', [
            'course' => $course->slug,
            'lesson' => $lesson->slug,
        ]))
            ->assertOk()
            ->assertJsonPath('data.course_id', $course->id)
            ->assertJsonPath('data.slug', 'shared-lesson-title')
            ->assertJsonPath('data.content', 'This free lesson content can be shown publicly.');
    }

    public function test_paid_course_lesson_public_api_returns_preview_without_full_content(): void
    {
        $course = Course::factory()->published()->create();
        $lesson = CourseLesson::factory()->published()->create([
            'course_id' => $course->id,
            'title' => 'Paid Lesson',
            'content' => 'one two three four five six seven eight',
            'is_free' => false,
            'is_paid' => true,
            'preview_word_limit' => 3,
        ]);

        $this->getJson(route('api.v1.courses.lessons.show', [
            'course' => $course->slug,
            'lesson' => $lesson->slug,
        ]))
            ->assertOk()
            ->assertJsonPath('data.content', null)
            ->assertJsonPath('data.preview', 'one two three...');
    }

    public function test_seo_payload_falls_back_to_content_fields(): void
    {
        $course = Course::factory()->make([
            'title' => 'Fallback Course Title',
            'short_description' => 'Fallback course description.',
            'seo_title' => null,
            'seo_description' => null,
        ]);

        $seo = $course->seoPayload();

        $this->assertSame('Fallback Course Title', $seo['title']);
        $this->assertSame('Fallback course description.', $seo['description']);
        $this->assertSame('Fallback Course Title', $seo['open_graph']['title']);
    }
}
