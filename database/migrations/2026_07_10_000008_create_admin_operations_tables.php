<?php

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
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action')->index();
            $table->nullableMorphs('auditable');
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_histories', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision')->index();
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('published_at');
            $table->text('rejection_reason')->nullable()->after('admin_notes');
        });

        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('published_at');
            $table->text('rejection_reason')->nullable()->after('admin_notes');
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('rejection_reason');
        });

        Schema::table('pages', function (Blueprint $table): void {
            $table->text('admin_notes')->nullable()->after('published_at');
            $table->text('rejection_reason')->nullable()->after('admin_notes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table): void {
            $table->dropColumn(['admin_notes', 'rejection_reason']);
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('admin_notes');
        });

        Schema::table('course_lessons', function (Blueprint $table): void {
            $table->dropColumn(['admin_notes', 'rejection_reason']);
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn(['admin_notes', 'rejection_reason']);
        });

        Schema::dropIfExists('approval_histories');
        Schema::dropIfExists('audit_logs');
    }
};
