<?php

use App\Enums\PublishStatus;
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
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('content')->nullable();
            $table->string('template')->nullable();
            $table->string('status', 32)->default(PublishStatus::Draft->value)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0);
            $this->seoColumns($table);
            $table->timestamps();
        });

        Schema::create('menus', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable()->index();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('type')->default('url');
            $table->string('url')->nullable();
            $table->string('route_name')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key')->index();
            $table->string('title')->nullable();
            $table->longText('body')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('group')->default('general')->index();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
        Schema::dropIfExists('pages');
    }

    private function seoColumns(Blueprint $table): void
    {
        $table->string('seo_title')->nullable();
        $table->text('seo_description')->nullable();
        $table->string('seo_image')->nullable();
        $table->string('canonical_url')->nullable();
        $table->string('og_title')->nullable();
        $table->text('og_description')->nullable();
        $table->string('twitter_title')->nullable();
        $table->text('twitter_description')->nullable();
        $table->json('schema')->nullable();
    }
};
