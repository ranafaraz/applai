<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_sends_verification_email(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name'                  => 'New User',
            'email'                 => 'new-user@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $user = User::where('email', 'new-user@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_unverified_user_cannot_compose_email(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('compose'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_can_compose_email(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('compose'))->assertOk();
    }

    public function test_unverified_user_can_still_use_dashboard_and_contacts(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();
        $this->actingAs($user)->get(route('contacts.index'))->assertOk();
    }

    public function test_dashboard_shows_onboarding_checklist_for_new_workspace(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Get set up')
            ->assertSee('Connect an email account');
    }

    public function test_onboarding_checklist_can_be_dismissed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('onboarding.dismiss'))->assertRedirect();

        $this->assertNotNull(UserSetting::where('user_id', $user->id)->value('onboarding_dismissed_at'));

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Get set up');
    }
}
