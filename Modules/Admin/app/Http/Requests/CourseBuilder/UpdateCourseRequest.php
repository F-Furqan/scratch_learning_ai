<?php

namespace Modules\Admin\Http\Requests\CourseBuilder;

use Illuminate\Validation\Rule;

final class UpdateCourseRequest extends AbstractCourseBuilderRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'course_category_id' => ['nullable', Rule::exists('course_categories', 'id')->whereNull('deleted_at')],
            'course_subcategory_id' => [
                'nullable',
                Rule::exists('course_subcategories', 'id')->where(
                    fn ($query) => $query
                        ->where('course_category_id', $this->input('course_category_id'))
                        ->whereNull('deleted_at'),
                ),
            ],
            'created_by' => ['nullable', Rule::exists('users', 'id')],
            'thumbnail_media_id' => ['nullable', Rule::exists('media_assets', 'id')],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'intro_video_url' => ['nullable', 'url', 'max:2048'],
            'level' => ['nullable', 'string', 'max:255'],
            'language' => ['required', 'string', 'max:16'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'seo_image' => ['nullable', 'url', 'max:2048'],
            'canonical_url' => ['nullable', 'url', 'max:2048'],
            'schema' => ['nullable', 'json'],
        ];
    }
}
