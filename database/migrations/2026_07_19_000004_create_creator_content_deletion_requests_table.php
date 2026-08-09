<?php

use App\Enums\CreatorContentDeletionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_content_deletion_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('requester_id')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('content', 'creator_del_req_content_idx');
            $table->string('status', 32)->default(CreatorContentDeletionStatus::Pending->value)->index();
            $table->text('reason')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->unique(['requester_id', 'content_type', 'content_id', 'status'], 'creator_del_req_unique');
            $table->index(['requester_id', 'status'], 'creator_del_req_requester_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_content_deletion_requests');
    }
};
