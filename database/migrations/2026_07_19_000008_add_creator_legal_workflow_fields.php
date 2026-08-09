<?php

use App\Enums\CopyrightTakedownStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogger_profiles', function (Blueprint $table): void {
            $table->boolean('is_verified_creator')->default(false)->after('status')->index();
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->timestamp('copyright_declaration_accepted_at')->nullable()->after('admin_notes');
            $table->ipAddress('copyright_declaration_ip')->nullable()->after('copyright_declaration_accepted_at');
            $table->text('copyright_declaration_user_agent')->nullable()->after('copyright_declaration_ip');
        });

        Schema::table('courses', function (Blueprint $table): void {
            $table->timestamp('copyright_declaration_accepted_at')->nullable()->after('rejection_reason');
            $table->ipAddress('copyright_declaration_ip')->nullable()->after('copyright_declaration_accepted_at');
            $table->text('copyright_declaration_user_agent')->nullable()->after('copyright_declaration_ip');
        });

        Schema::create('copyright_takedown_requests', function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('reportable', 'copyright_reportable_idx');
            $table->string('status', 32)->default(CopyrightTakedownStatus::Submitted->value)->index();
            $table->string('claimant_name');
            $table->string('claimant_email')->index();
            $table->string('claimant_company')->nullable();
            $table->string('rights_owner');
            $table->string('original_work_url')->nullable();
            $table->string('infringing_url');
            $table->string('content_title')->nullable();
            $table->text('description');
            $table->boolean('good_faith_confirmed')->default(false);
            $table->boolean('accuracy_confirmed')->default(false);
            $table->string('signature');
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'copyright_takedown_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('copyright_takedown_requests');

        Schema::table('courses', function (Blueprint $table): void {
            $table->dropColumn([
                'copyright_declaration_accepted_at',
                'copyright_declaration_ip',
                'copyright_declaration_user_agent',
            ]);
        });

        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn([
                'copyright_declaration_accepted_at',
                'copyright_declaration_ip',
                'copyright_declaration_user_agent',
            ]);
        });

        Schema::table('blogger_profiles', function (Blueprint $table): void {
            $table->dropColumn('is_verified_creator');
        });
    }
};
