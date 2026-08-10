<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use App\Enums\LearningResourceAccess;
use App\Enums\PaymentBillingInterval;
use App\Enums\PublishStatus;
use App\Enums\QuizQuestionType;
use App\Enums\VideoType;
use App\Models\Course;
use Illuminate\Validation\Rule;

final class MutateItemRequest extends AbstractCourseBuilderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $course = $this->route('course');
        $courseId = $course instanceof Course ? $course->id : 0;

        return match ((string) $this->route('kind')) {
            'sections' => [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'status' => ['required', Rule::enum(PublishStatus::class)],
            ],
            'lessons' => [
                'course_section_id' => ['nullable', Rule::exists('course_sections', 'id')->where('course_id', $courseId)],
                'title' => ['required', 'string', 'max:255'],
                'order_number' => ['nullable', 'integer', 'min:0'],
                'content' => ['nullable', 'string'],
                'video_type' => ['required', Rule::enum(VideoType::class)],
                'video_url' => ['nullable', 'url', 'max:2048'],
                'video_file_id' => ['nullable', Rule::exists('media_assets', 'id')],
                'is_free' => ['required', 'boolean'],
                'is_paid' => ['required', 'boolean'],
                'preview_word_limit' => ['nullable', 'integer', 'min:0'],
                'status' => ['required', Rule::enum(PublishStatus::class)],
                'seo_title' => ['nullable', 'string', 'max:255'],
                'seo_description' => ['nullable', 'string', 'max:500'],
            ],
            'resources' => [
                'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $courseId)],
                'media_asset_id' => ['nullable', Rule::exists('media_assets', 'id')],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'type' => ['required', 'string', 'max:40'],
                'access_level' => ['required', Rule::enum(LearningResourceAccess::class)],
                'file_path' => ['nullable', 'string', 'max:2048'],
                'external_url' => ['nullable', 'url', 'max:2048'],
                'is_downloadable' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'faqs' => [
                'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $courseId)],
                'question' => ['required', 'string', 'max:255'],
                'answer' => ['required', 'string', 'max:4000'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
                'status' => ['required', Rule::enum(PublishStatus::class)],
            ],
            'quizzes' => [
                'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $courseId)],
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'pass_score' => ['required', 'integer', 'min:0', 'max:100'],
                'max_attempts' => ['required', 'integer', 'min:1', 'max:100'],
                'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
                'is_required' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'quiz-questions' => [
                'quiz_id' => ['required', Rule::exists('quizzes', 'id')->where('course_id', $courseId)],
                'question' => ['required', 'string', 'max:2000'],
                'type' => ['required', Rule::enum(QuizQuestionType::class)],
                'points' => ['required', 'integer', 'min:1', 'max:1000'],
                'options_json' => ['nullable', 'json'],
                'correct_answer_json' => ['required', 'json'],
                'explanation' => ['nullable', 'string', 'max:4000'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'assignments' => [
                'course_lesson_id' => ['nullable', Rule::exists('course_lessons', 'id')->where('course_id', $courseId)],
                'title' => ['required', 'string', 'max:255'],
                'instructions' => ['required', 'string'],
                'pass_score' => ['required', 'integer', 'min:0'],
                'max_points' => ['required', 'integer', 'min:1'],
                'due_days_after_enrollment' => ['nullable', 'integer', 'min:0'],
                'allow_file_uploads' => ['required', 'boolean'],
                'is_required' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'prices' => [
                'name' => ['required', 'string', 'max:255'],
                'paddle_price_id' => ['nullable', 'string', 'max:255'],
                'billing_interval' => ['required', Rule::enum(PaymentBillingInterval::class)],
                'is_recurring' => ['required', 'boolean'],
                'currency' => ['required', 'string', 'size:3'],
                'amount' => ['required', 'integer', 'min:0'],
                'trial_days' => ['nullable', 'integer', 'min:0'],
                'is_active' => ['required', 'boolean'],
            ],
            default => ['kind' => ['prohibited']],
        };
    }
}
