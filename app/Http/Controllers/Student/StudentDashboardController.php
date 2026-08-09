<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\CourseEnrollment;
use App\Models\LessonBookmark;
use App\Models\LessonNote;
use App\Models\LessonProgress;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StudentDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $enrollments = CourseEnrollment::query()
            ->with(['course.thumbnail', 'lastLesson'])
            ->where('user_id', $user->id)
            ->latest('updated_at')
            ->take(8)
            ->get()
            ->map(fn (CourseEnrollment $enrollment): array => [
                'id' => $enrollment->id,
                'course_title' => $enrollment->course?->title,
                'course_url' => $enrollment->course ? route('public.courses.show', $enrollment->course->slug) : null,
                'last_lesson_title' => $enrollment->lastLesson?->title,
                'last_lesson_url' => $enrollment->course && $enrollment->lastLesson
                    ? route('public.lessons.show', [$enrollment->course->slug, $enrollment->lastLesson->slug])
                    : null,
                'status' => $this->enumValue($enrollment->getAttribute('status')),
                'progress_percent' => $enrollment->progress_percent,
                'started_at' => $this->isoDate($enrollment->getAttribute('started_at')),
                'completed_at' => $this->isoDate($enrollment->getAttribute('completed_at')),
            ]);

        $continueWatching = LessonProgress::query()
            ->with(['course', 'lesson'])
            ->where('user_id', $user->id)
            ->latest('last_watched_at')
            ->take(6)
            ->get()
            ->map(fn (LessonProgress $progress): array => [
                'id' => $progress->id,
                'course_title' => $progress->course?->title,
                'lesson_title' => $progress->lesson?->title,
                'lesson_url' => $progress->course && $progress->lesson
                    ? route('public.lessons.show', [$progress->course->slug, $progress->lesson->slug])
                    : null,
                'status' => $this->enumValue($progress->getAttribute('status')),
                'progress_percent' => $progress->progress_percent,
                'progress_seconds' => $progress->progress_seconds,
                'duration_seconds' => $progress->duration_seconds,
                'last_watched_at' => $this->isoDate($progress->getAttribute('last_watched_at')),
            ]);

        $bookmarks = LessonBookmark::query()
            ->with(['course', 'lesson'])
            ->where('user_id', $user->id)
            ->latest('saved_at')
            ->take(8)
            ->get()
            ->map(fn (LessonBookmark $bookmark): array => [
                'id' => $bookmark->id,
                'label' => $bookmark->label,
                'course_title' => $bookmark->course?->title,
                'lesson_title' => $bookmark->lesson?->title,
                'lesson_url' => $bookmark->course && $bookmark->lesson
                    ? route('public.lessons.show', [$bookmark->course->slug, $bookmark->lesson->slug])
                    : null,
                'saved_at' => $this->isoDate($bookmark->getAttribute('saved_at')),
            ]);

        $notes = LessonNote::query()
            ->with(['course', 'lesson'])
            ->where('user_id', $user->id)
            ->latest()
            ->take(6)
            ->get()
            ->map(fn (LessonNote $note): array => [
                'id' => $note->id,
                'body' => $note->body,
                'course_title' => $note->course?->title,
                'lesson_title' => $note->lesson?->title,
                'lesson_url' => $note->course && $note->lesson
                    ? route('public.lessons.show', [$note->course->slug, $note->lesson->slug])
                    : null,
                'created_at' => $this->isoDate($note->getAttribute('created_at')),
            ]);

        $certificates = Certificate::query()
            ->with('course')
            ->where('user_id', $user->id)
            ->latest('issued_at')
            ->take(6)
            ->get()
            ->map(fn (Certificate $certificate): array => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'course_title' => $certificate->course?->title,
                'verification_url' => $certificate->verificationUrl(),
                'status' => $this->enumValue($certificate->getAttribute('status')),
                'issued_at' => $this->isoDate($certificate->getAttribute('issued_at')),
            ]);

        return Inertia::render('student/Dashboard', [
            'stats' => [
                'active_courses' => CourseEnrollment::query()->where('user_id', $user->id)->where('status', 'active')->count(),
                'completed_courses' => CourseEnrollment::query()->where('user_id', $user->id)->where('status', 'completed')->count(),
                'saved_lessons' => LessonBookmark::query()->where('user_id', $user->id)->count(),
                'certificates' => Certificate::query()->where('user_id', $user->id)->count(),
            ],
            'enrollments' => $enrollments,
            'continue_watching' => $continueWatching,
            'bookmarks' => $bookmarks,
            'notes' => $notes,
            'certificates' => $certificates,
        ]);
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof BackedEnum ? (string) $value->value : (is_string($value) ? $value : null);
    }

    private function isoDate(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        return is_string($value) ? $value : null;
    }
}
