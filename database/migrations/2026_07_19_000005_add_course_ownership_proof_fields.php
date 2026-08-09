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
        Schema::table('courses', function (Blueprint $table): void {
            $table->foreignId('ownership_video_media_id')
                ->nullable()
                ->after('thumbnail_media_id')
                ->constrained('media_assets')
                ->nullOnDelete();
            $table->string('ownership_video_url')->nullable()->after('ownership_video_media_id');
            $table->text('ownership_statement')->nullable()->after('ownership_video_url');
            $table->timestamp('ownership_confirmed_at')->nullable()->after('ownership_statement');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('ownership_video_media_id');
            $table->dropColumn([
                'ownership_video_url',
                'ownership_statement',
                'ownership_confirmed_at',
            ]);
        });
    }
};
