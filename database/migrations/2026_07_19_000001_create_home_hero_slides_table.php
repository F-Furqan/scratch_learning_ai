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
        Schema::create('home_hero_slides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('media_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('eyebrow')->nullable();
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('button_label')->nullable();
            $table->string('target_url');
            $table->string('image_url')->nullable();
            $table->string('image_alt')->nullable();
            $table->string('text_position', 32)->default('left');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('opens_in_new_tab')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_hero_slides');
    }
};
