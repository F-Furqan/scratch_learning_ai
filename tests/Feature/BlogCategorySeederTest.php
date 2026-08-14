<?php

namespace Tests\Feature;

use App\Models\BlogCategory;
use Database\Seeders\BlogCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_adds_missing_categories_without_overwriting_existing_records(): void
    {
        BlogCategory::query()->create([
            'name' => 'News',
            'slug' => 'news',
            'description' => 'Admin-managed description.',
            'is_active' => false,
            'seo_title' => 'Custom News SEO',
        ]);

        $this->seed(BlogCategorySeeder::class);
        $countAfterFirstRun = BlogCategory::query()->count();

        $this->seed(BlogCategorySeeder::class);

        $this->assertSame($countAfterFirstRun, BlogCategory::query()->count());
        $this->assertSame(count(BlogCategorySeeder::names()), BlogCategory::query()->count());
        $this->assertDatabaseHas('blog_categories', [
            'slug' => 'news',
            'description' => 'Admin-managed description.',
            'is_active' => false,
            'seo_title' => 'Custom News SEO',
        ]);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'laravel']);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'games-blog']);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'game-development']);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'ai']);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'cpp']);
        $this->assertDatabaseHas('blog_categories', ['slug' => 'c-sharp']);
    }
}
