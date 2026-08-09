<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('database_backups', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 40)->index();
            $table->string('connection_name', 80);
            $table->string('database_name');
            $table->string('status', 30)->index();
            $table->string('verification_status', 30)->default('not_requested')->index();
            $table->string('disk', 80)->nullable();
            $table->string('path', 1024)->nullable();
            $table->string('provider_reference')->nullable()->index();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->text('failure_reason')->nullable();
            $table->text('verification_failure_reason')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('storage_deleted_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'db_backups_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('database_backups');
    }
};
