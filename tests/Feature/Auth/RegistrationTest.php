<?php

namespace Tests\Feature\Auth;

use App\Enums\BloggerStatus;
use App\Enums\RoleName;
use App\Models\BloggerProfile;
use App\Models\CreatorAgreementAcceptance;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('accountType', 'student'),
            );
    }

    public function test_student_and_creator_registration_screens_can_be_rendered()
    {
        $this->get(route('register.student'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('accountType', 'student'),
            );

        $this->get(route('register.creator'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/Register')
                ->where('accountType', 'creator'),
            );
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'account_type' => 'student',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'test@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::Student->value));
        $this->assertDatabaseHas(StudentProfile::class, [
            'user_id' => $user->id,
        ]);
    }

    public function test_creator_users_can_register_with_pending_profile()
    {
        $response = $this
            ->withHeader('User-Agent', 'Registration Test Browser')
            ->post(route('register.store'), [
                'name' => 'Creator User',
                'email' => 'creator@example.com',
                'account_type' => 'creator',
                'expertise' => 'Laravel course design',
                'linkedin_url' => 'https://www.linkedin.com/in/creator-user',
                'website_url' => 'https://creator.example.com',
                'phone' => '+1 555 0199',
                'application_reason' => 'I want to publish industrial Laravel courses.',
                'creator_agreement_accepted' => '1',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::query()->where('email', 'creator@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::Blogger->value));
        $this->assertFalse($user->hasRole(RoleName::Student->value));
        $this->assertDatabaseHas(BloggerProfile::class, [
            'user_id' => $user->id,
            'status' => BloggerStatus::Pending->value,
            'expertise' => 'Laravel course design',
            'linkedin_url' => 'https://www.linkedin.com/in/creator-user',
            'website_url' => 'https://creator.example.com',
            'phone' => '+1 555 0199',
            'application_reason' => 'I want to publish industrial Laravel courses.',
        ]);

        $this->assertDatabaseHas(CreatorAgreementAcceptance::class, [
            'user_id' => $user->id,
            'terms_version' => config('platform.creator_agreement.version'),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Registration Test Browser',
        ]);
        $this->assertTrue($user->fresh()->hasAcceptedCreatorAgreement());
    }

    public function test_creator_registration_requires_creator_agreement_acceptance()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Creator User',
            'email' => 'creator-without-agreement@example.com',
            'account_type' => 'creator',
            'expertise' => 'Laravel course design',
            'linkedin_url' => 'https://www.linkedin.com/in/creator-without-agreement',
            'website_url' => 'https://creator.example.com',
            'phone' => '+1 555 0199',
            'application_reason' => 'I want to publish industrial Laravel courses.',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('creator_agreement_accepted');

        $this->assertGuest();
        $this->assertDatabaseMissing(User::class, [
            'email' => 'creator-without-agreement@example.com',
        ]);
    }
}
