<?php

namespace Tests\Feature;

use App\Enums\BloggerStatus;
use App\Enums\RoleName;
use App\Models\BloggerProfile;
use App\Models\BlogPost;
use App\Models\CreatorAgreementAcceptance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreatorAgreementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_existing_creator_can_accept_current_agreement(): void
    {
        $creator = $this->creator();

        $this->actingAs($creator)
            ->get(route('creator.agreement.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('creator/Agreement')
                ->where('agreement.version', config('platform.creator_agreement.version'))
                ->where('agreement.accepted', false),
            );

        $this->actingAs($creator)
            ->withHeader('User-Agent', 'Agreement Test Browser')
            ->post(route('creator.agreement.store'), [
                'creator_agreement_accepted' => '1',
            ])
            ->assertRedirect(route('creator.dashboard', absolute: false));

        $this->assertDatabaseHas(CreatorAgreementAcceptance::class, [
            'user_id' => $creator->id,
            'terms_version' => config('platform.creator_agreement.version'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Agreement Test Browser',
        ]);
        $this->assertTrue($creator->fresh()->hasAcceptedCreatorAgreement());
    }

    public function test_creator_upload_routes_require_current_agreement_acceptance(): void
    {
        Route::middleware(['web', 'auth', 'role:blogger', 'creator.agreement'])
            ->post('/creator/uploads/smoke', fn () => response('ok'))
            ->name('creator.uploads.smoke');

        $creator = $this->creator();

        $this->actingAs($creator)
            ->post('/creator/uploads/smoke')
            ->assertRedirect(route('creator.agreement.show', absolute: false));

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $creator->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        $this->actingAs($creator)
            ->post('/creator/uploads/smoke')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_acceptance_is_version_specific(): void
    {
        $creator = $this->creator();

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $creator->id,
            'terms_version' => '2026-01-01',
        ]);

        $this->assertFalse($creator->fresh()->hasAcceptedCreatorAgreement());
    }

    public function test_creator_content_policy_requires_current_agreement(): void
    {
        $creator = $this->creator();

        BloggerProfile::factory()->create([
            'user_id' => $creator->id,
            'status' => BloggerStatus::Approved,
        ]);

        $this->assertFalse(Gate::forUser($creator)->allows('create', BlogPost::class));

        CreatorAgreementAcceptance::factory()->create([
            'user_id' => $creator->id,
            'terms_version' => config('platform.creator_agreement.version'),
        ]);

        $this->assertTrue(Gate::forUser($creator->fresh())->allows('create', BlogPost::class));
    }

    private function creator(): User
    {
        Role::findOrCreate(RoleName::Blogger->value, 'web');

        $user = User::factory()->create();
        $user->assignRole(RoleName::Blogger->value);

        return $user;
    }
}
