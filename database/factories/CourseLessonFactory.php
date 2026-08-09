<?php

namespace Database\Factories;

use App\Enums\PublishStatus;
use App\Enums\VideoType;
use App\Models\Course;
use App\Models\CourseLesson;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLesson>
 */
class CourseLessonFactory extends Factory
{
    protected $model = CourseLesson::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(5);

        return [
            'course_id' => Course::factory(),
            'course_section_id' => null,
            'title' => $title,
            'order_number' => fake()->numberBetween(1, 30),
            'content' => fake()->paragraphs(8, true),
            'video_type' => VideoType::Url,
            'video_url' => 'https://videos.example.com/'.fake()->uuid(),
            'video_file_id' => null,
            'is_free' => false,
            'is_paid' => true,
            'preview_word_limit' => 120,
            'allow_comments' => true,
            'allow_questions' => true,
            'status' => PublishStatus::Draft,
            'published_at' => null,
            'seo_title' => $title,
            'seo_description' => fake()->sentence(18),
            'schema' => [
                '@type' => 'LearningResource',
            ],
        ];
    }

    public function free(): static
    {
        return $this->state(fn (): array => [
            'is_free' => true,
            'is_paid' => false,
            'preview_word_limit' => null,
        ]);
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PublishStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
