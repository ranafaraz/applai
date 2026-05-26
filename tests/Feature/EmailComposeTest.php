<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailComposeTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_body_editor_has_plain_text_fallback(): void
    {
        $user = User::factory()->create();
        EmailAccount::factory()->create(['user_id' => $user->id, 'is_active' => true]);

        $response = $this->actingAs($user)->get(route('compose'));

        $response
            ->assertOk()
            ->assertSee('id="composeEditor"', false)
            ->assertSee('id="composeBody" class="hidden"', false)
            ->assertSee('showPlainBodyEditor', false)
            ->assertSee('quillBootAttempts', false)
            ->assertSee('id="compose-subject"', false)
            ->assertDontSee('id="subject"', false)
            ->assertDontSee('id="composeBody" class="hidden" required', false);
    }

    public function test_edit_body_editor_has_plain_text_fallback(): void
    {
        $user = User::factory()->create();
        $account = EmailAccount::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $email = EmailMessage::factory()->create([
            'user_id' => $user->id,
            'email_account_id' => $account->id,
            'status' => 'draft',
            'sent_at' => null,
        ]);

        $response = $this->actingAs($user)->get(route('emails.edit', $email));

        $response
            ->assertOk()
            ->assertSee('id="composeEditor"', false)
            ->assertSee('id="composeBody" class="hidden"', false)
            ->assertSee('showPlainBodyEditor', false)
            ->assertSee('quillBootAttempts', false)
            ->assertSee('id="compose-subject"', false)
            ->assertDontSee('id="subject"', false)
            ->assertDontSee('id="composeBody" class="hidden" required', false);
    }
}
