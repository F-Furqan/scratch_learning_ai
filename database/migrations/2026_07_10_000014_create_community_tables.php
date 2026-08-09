<?php

use App\Enums\CommunityContentStatus;
use App\Enums\CommunityReactionType;
use App\Enums\CommunityReportStatus;
use App\Enums\CommunityVisibility;
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
        Schema::create('lesson_question_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->string('status', 32)->default(CommunityContentStatus::Pending->value)->index();
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('course_questions', function (Blueprint $table): void {
            $table->foreignId('accepted_answer_id')
                ->nullable()
                ->after('answer')
                ->constrained('lesson_question_answers')
                ->nullOnDelete();
        });

        Schema::create('discussion_forums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('course_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('visibility', 32)->default(CommunityVisibility::Public->value)->index();
            $table->string('status', 32)->default(CommunityContentStatus::Approved->value)->index();
            $table->unsignedInteger('threads_count')->default(0);
            $table->unsignedInteger('posts_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['course_id', 'slug']);
            $table->unique(['course_category_id', 'slug']);
        });

        Schema::create('discussion_threads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discussion_forum_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('body');
            $table->string('status', 32)->default(CommunityContentStatus::Pending->value)->index();
            $table->boolean('is_pinned')->default(false)->index();
            $table->boolean('is_locked')->default(false)->index();
            $table->unsignedInteger('replies_count')->default(0);
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['discussion_forum_id', 'slug']);
        });

        Schema::create('discussion_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('discussion_thread_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('discussion_posts')->cascadeOnDelete();
            $table->text('body');
            $table->string('status', 32)->default(CommunityContentStatus::Pending->value)->index();
            $table->unsignedInteger('upvotes_count')->default(0);
            $table->timestamps();
        });

        Schema::create('community_groups', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('visibility', 32)->default(CommunityVisibility::PaidMembers->value)->index();
            $table->string('status', 32)->default(CommunityContentStatus::Approved->value)->index();
            $table->boolean('requires_paid_access')->default(true)->index();
            $table->unsignedInteger('members_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('community_group_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member')->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['community_group_id', 'user_id']);
        });

        Schema::create('community_reactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reactable');
            $table->string('type', 32)->default(CommunityReactionType::Upvote->value);
            $table->smallInteger('value')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'reactable_type', 'reactable_id', 'type'], 'community_reactions_unique');
        });

        Schema::create('community_reputation_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('points')->default(0);
            $table->string('level', 32)->default('newcomer');
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('reputation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('subject');
            $table->string('type', 64)->index();
            $table->integer('points');
            $table->string('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('blocked_words', function (Blueprint $table): void {
            $table->id();
            $table->string('word')->unique();
            $table->string('match_type', 32)->default('contains');
            $table->unsignedTinyInteger('severity')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_queue_items', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('subject');
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default(CommunityContentStatus::Pending->value)->index();
            $table->string('reason')->nullable();
            $table->unsignedTinyInteger('spam_score')->default(0);
            $table->json('matched_terms')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('content_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('reportable');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default(CommunityReportStatus::Open->value)->index();
            $table->string('reason', 128);
            $table->text('details')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::dropIfExists('moderation_queue_items');
        Schema::dropIfExists('blocked_words');
        Schema::dropIfExists('reputation_events');
        Schema::dropIfExists('community_reputation_scores');
        Schema::dropIfExists('community_reactions');
        Schema::dropIfExists('community_group_members');
        Schema::dropIfExists('community_groups');
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_threads');
        Schema::dropIfExists('discussion_forums');

        Schema::table('course_questions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('accepted_answer_id');
        });

        Schema::dropIfExists('lesson_question_answers');
    }
};
