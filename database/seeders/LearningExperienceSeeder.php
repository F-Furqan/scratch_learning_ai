<?php

namespace Database\Seeders;

use App\Enums\DripReleaseType;
use App\Enums\LearningCatalogStatus;
use App\Enums\LearningResourceAccess;
use App\Enums\QuizQuestionType;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\CourseResource;
use App\Models\LearningPath;
use App\Models\LessonDripSchedule;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SkillTrack;
use Illuminate\Database\Seeder;

class LearningExperienceSeeder extends Seeder
{
    /**
     * Seed starter learning experience records for the demo course.
     */
    public function run(): void
    {
        $course = Course::query()
            ->where('slug', 'industrial-laravel-foundations')
            ->with('lessons')
            ->first();

        if (! $course || $course->lessons->isEmpty()) {
            return;
        }

        $lesson = $course->lessons->first();

        CourseResource::query()->firstOrCreate(
            ['course_id' => $course->id, 'course_lesson_id' => $lesson->id, 'title' => 'Architecture Checklist'],
            [
                'description' => 'A practical checklist for validating Laravel learning-platform architecture.',
                'type' => 'checklist',
                'access_level' => LearningResourceAccess::Enrolled,
                'external_url' => 'https://resources.example.com/industrial-laravel-checklist.pdf',
                'is_downloadable' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        $quiz = Quiz::query()->firstOrCreate(
            ['course_id' => $course->id, 'course_lesson_id' => $lesson->id, 'title' => 'Content Modeling Check'],
            [
                'description' => 'Validate the key ideas from content domain modeling.',
                'pass_score' => 70,
                'max_attempts' => 3,
                'is_required' => true,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        QuizQuestion::query()->firstOrCreate(
            ['quiz_id' => $quiz->id, 'question' => 'What should be modeled before controllers and UI?'],
            [
                'type' => QuizQuestionType::MultipleChoice,
                'points' => 1,
                'options' => ['The domain contracts', 'Button colors', 'A billing receipt template'],
                'correct_answer' => ['The domain contracts'],
                'explanation' => 'Durable domain models give the platform a stable contract.',
                'sort_order' => 1,
            ],
        );

        Assignment::query()->firstOrCreate(
            ['course_id' => $course->id, 'course_lesson_id' => $lesson->id, 'title' => 'Map Your Learning Domain'],
            [
                'instructions' => 'Describe the core models, statuses, and access rules for a production LMS module.',
                'pass_score' => 70,
                'max_points' => 100,
                'due_days_after_enrollment' => 7,
                'allow_file_uploads' => false,
                'is_required' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
        );

        LessonDripSchedule::query()->updateOrCreate(
            ['course_lesson_id' => $lesson->id],
            [
                'course_id' => $course->id,
                'release_type' => DripReleaseType::Immediate,
                'release_after_days' => null,
                'release_at' => null,
                'is_active' => true,
            ],
        );

        $path = LearningPath::query()->firstOrCreate(
            ['slug' => 'laravel-platform-architect'],
            [
                'title' => 'Laravel Platform Architect',
                'description' => 'A sequenced path for building robust Laravel learning products.',
                'status' => LearningCatalogStatus::Active,
                'sort_order' => 1,
                'metadata' => ['audience' => 'engineering teams'],
            ],
        );

        $path->courses()->syncWithoutDetaching([$course->id => ['sort_order' => 1]]);

        $track = SkillTrack::query()->firstOrCreate(
            ['slug' => 'production-lms-engineering'],
            [
                'title' => 'Production LMS Engineering',
                'description' => 'Skills for content, access, commerce, and learning analytics.',
                'status' => LearningCatalogStatus::Active,
                'sort_order' => 1,
                'metadata' => ['skill' => 'lms-architecture'],
            ],
        );

        $track->courses()->syncWithoutDetaching([$course->id => ['sort_order' => 1]]);

        $bundle = CourseBundle::query()->firstOrCreate(
            ['slug' => 'industrial-learning-platform-bundle'],
            [
                'title' => 'Industrial Learning Platform Bundle',
                'description' => 'A bundled route through platform architecture, commerce, and operations.',
                'price' => 299,
                'status' => LearningCatalogStatus::Active,
                'sort_order' => 1,
                'metadata' => ['team_ready' => true],
            ],
        );

        $bundle->courses()->syncWithoutDetaching([$course->id => ['sort_order' => 1]]);
    }
}
