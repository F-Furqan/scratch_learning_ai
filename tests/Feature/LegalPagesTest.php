<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Page;
use Database\Seeders\ContentSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_terms_and_privacy_pages_include_creator_legal_rules(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ContentSeeder::class);

        $terms = Page::query()->where('slug', 'terms-and-conditions')->firstOrFail();
        $privacy = Page::query()->where('slug', 'privacy-policy')->firstOrFail();

        $this->assertStringContainsString('does not pay creators', $terms->content);
        $this->assertStringContainsString('promotional and educational', $terms->content);
        $this->assertStringContainsString('creator agreement acceptance records', $privacy->content);

        $this->assertDatabaseHas(ContentBlock::class, [
            'page_id' => $terms->id,
            'key' => 'creator-content-rights',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas(ContentBlock::class, [
            'page_id' => $privacy->id,
            'key' => 'agreement-records',
            'is_active' => true,
        ]);

        $this->get(route('public.pages.terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/pages/Show')
                ->where('page.slug', 'terms-and-conditions')
                ->where('page.blocks.1.key', 'no-creator-payments'),
            );
    }
}
