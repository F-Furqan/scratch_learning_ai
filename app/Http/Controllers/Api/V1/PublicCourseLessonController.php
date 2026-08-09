<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\CourseLessonResource;
use App\Models\Course;
use App\Models\CourseLesson;

class PublicCourseLessonController extends Controller
{
    public function show(Course $course, CourseLesson $lesson): CourseLessonResource
    {
        abort_unless($course->isPublished(), 404);
        abort_unless($lesson->course_id === $course->id && $lesson->isPublished(), 404);

        $lesson->load([
            'course',
            'section',
            'videoFile',
            'faqs' => fn ($query) => $query->published()->orderBy('sort_order'),
        ]);

        return CourseLessonResource::make($lesson);
    }
}
