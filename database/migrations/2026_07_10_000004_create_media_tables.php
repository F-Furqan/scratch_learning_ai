<?php

use App\Enums\MediaVisibility;
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
        Schema::create('media_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('path')->nullable();
            $table->timestamps();

            $table->unique(['parent_id', 'slug']);
        });

        Schema::create('media_assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('url')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('caption')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('visibility', 32)->default(MediaVisibility::Public->value)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['disk', 'path']);
        });

        Schema::create('media_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->constrained()->cascadeOnDelete();
            $table->morphs('mediable');
            $table->string('collection')->nullable();
            $table->timestamps();

            $table->index(['collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_usages');
        Schema::dropIfExists('media_assets');
        Schema::dropIfExists('media_folders');
    }
};
