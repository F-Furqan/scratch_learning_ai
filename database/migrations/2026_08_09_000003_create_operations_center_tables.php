<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_scheduler_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100)->index('ops_sched_heartbeat_name_idx');
            $table->string('status', 24)->index('ops_sched_heartbeat_status_idx');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('operations_health_check_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('check_name', 100)->index('ops_health_check_name_idx');
            $table->string('status', 24)->index('ops_health_check_status_idx');
            $table->json('metadata')->nullable();
            $table->timestamp('checked_at')->index('ops_health_checked_at_idx');
            $table->timestamps();
        });

        Schema::create('operations_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 191)->unique('ops_alert_key_unique');
            $table->string('source', 50)->index('ops_alert_source_idx');
            $table->string('severity', 24)->index('ops_alert_severity_idx');
            $table->string('status', 24)->index('ops_alert_status_idx');
            $table->string('title');
            $table->text('message');
            $table->unsignedInteger('occurrence_count')->default(1);
            $table->timestamp('first_detected_at');
            $table->timestamp('last_detected_at');
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('operations_log_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('level', 24)->index('ops_log_level_idx');
            $table->string('channel', 80)->index('ops_log_channel_idx');
            $table->string('environment', 40)->index('ops_log_environment_idx');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->index('ops_log_occurred_at_idx');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_log_entries');
        Schema::dropIfExists('operations_alerts');
        Schema::dropIfExists('operations_health_check_runs');
        Schema::dropIfExists('operations_scheduler_heartbeats');
    }
};
