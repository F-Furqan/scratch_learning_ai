<?php

use App\Enums\AssignmentSubmissionStatus;
use App\Enums\CertificateStatus;
use App\Enums\CourseEnrollmentStatus;
use App\Enums\DripReleaseType;
use App\Enums\LearningCatalogStatus;
use App\Enums\LearningResourceAccess;
use App\Enums\LessonProgressStatus;
use App\Enums\QuizAttemptStatus;
use App\Enums\QuizQuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_lesson_id')->nullable()->constrained('course_lessons')->nullOnDelete();
            $table->string('source', 32)->default('manual')->index();
            $table->string('status', 32)->default(CourseEnrollmentStatus::Active->value)->index();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('lesson_drip_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->string('release_type', 40)->default(DripReleaseType::Immediate->value)->index();
            $table->unsignedInteger('release_after_days')->nullable();
            $table->timestamp('release_at')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique('course_lesson_id');
        });

        Schema::create('lesson_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(LessonProgressStatus::NotStarted->value)->index();
            $table->unsignedInteger('progress_seconds')->default(0);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_watched_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_lesson_id']);
            $table->index(['course_id', 'status']);
        });

        Schema::create('lesson_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_private')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'course_lesson_id']);
        });

        Schema::create('lesson_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->timestamp('saved_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['user_id', 'course_lesson_id']);
        });

        Schema::create('course_resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 40)->default('download');
            $table->string('access_level', 32)->default(LearningResourceAccess::Enrolled->value)->index();
            $table->string('file_path')->nullable();
            $table->string('external_url')->nullable();
            $table->boolean('is_downloadable')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'course_lesson_id']);
        });

        Schema::create('quizzes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedSmallInteger('max_attempts')->default(3);
            $table->unsignedSmallInteger('time_limit_minutes')->nullable();
            $table->boolean('is_required')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->string('type', 40)->default(QuizQuestionType::MultipleChoice->value);
            $table->unsignedSmallInteger('points')->default(1);
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number')->default(1);
            $table->string('status', 32)->default(QuizAttemptStatus::InProgress->value)->index();
            $table->unsignedInteger('score')->default(0);
            $table->unsignedInteger('max_score')->default(0);
            $table->boolean('passed')->default(false)->index();
            $table->json('answers')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'quiz_id', 'attempt_number']);
        });

        Schema::create('assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('instructions');
            $table->unsignedTinyInteger('pass_score')->default(70);
            $table->unsignedInteger('max_points')->default(100);
            $table->unsignedInteger('due_days_after_enrollment')->nullable();
            $table->boolean('allow_file_uploads')->default(false);
            $table->boolean('is_required')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('assignment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_lesson_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default(AssignmentSubmissionStatus::Submitted->value)->index();
            $table->longText('submitted_text')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->boolean('passed')->nullable()->index();
            $table->longText('feedback')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'user_id']);
        });

        Schema::create('certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->string('verification_code')->unique();
            $table->string('status', 32)->default(CertificateStatus::Active->value)->index();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('learning_paths', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 32)->default(LearningCatalogStatus::Draft->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('skill_tracks', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status', 32)->default(LearningCatalogStatus::Draft->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('course_bundles', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 32)->default(LearningCatalogStatus::Draft->value)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('learning_path_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['learning_path_id', 'course_id']);
        });

        Schema::create('skill_track_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('skill_track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['skill_track_id', 'course_id']);
        });

        Schema::create('course_bundle_courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['course_bundle_id', 'course_id']);
        });

        Schema::create('learning_path_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(CourseEnrollmentStatus::Active->value)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_path_id', 'user_id']);
        });

        Schema::create('course_bundle_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default(CourseEnrollmentStatus::Active->value)->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['course_bundle_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_bundle_enrollments');
        Schema::dropIfExists('learning_path_enrollments');
        Schema::dropIfExists('course_bundle_courses');
        Schema::dropIfExists('skill_track_courses');
        Schema::dropIfExists('learning_path_courses');
        Schema::dropIfExists('course_bundles');
        Schema::dropIfExists('skill_tracks');
        Schema::dropIfExists('learning_paths');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
        Schema::dropIfExists('course_resources');
        Schema::dropIfExists('lesson_bookmarks');
        Schema::dropIfExists('lesson_notes');
        Schema::dropIfExists('lesson_progress');
        Schema::dropIfExists('lesson_drip_schedules');
        Schema::dropIfExists('course_enrollments');
    }
};
