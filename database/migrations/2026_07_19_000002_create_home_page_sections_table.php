<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_page_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 80)->index();
            $table->string('eyebrow')->nullable();
            $table->string('title')->nullable();
            $table->text('subtitle')->nullable();
            $table->text('body')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url', 2048)->nullable();
            $table->string('background', 40)->default('white');
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_page_sections');
    }
};
