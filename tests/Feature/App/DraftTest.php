<?php

namespace Tests\Feature\App;

use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mobile API (/api/app/v1) AI drafts CRUD + generate + send-payload
 * ownership isolation (Milestone 4). No mail is ever sent from this API.
 */
class DraftTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['*']);

        return $user;
    }

    private function fakeOpenAi(string $subject = 'AI Subject', string $body = 'AI body text.'): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => [
                        'content' => json_encode(['subject' => $subject, 'body' => $body]),
                    ],
                ]],
            ], 200),
        ]);
    }

    private function makeDraft(User $user, array $overrides = []): EmailMessage
    {
        return EmailMessage::factory()->create(array_merge([
            'user_id'   => $user->id,
            'status'    => 'draft',
            'direction' => 'outbound',
            'to_email'  => 'recipient@example.com',
            'subject'   => 'Original Subject',
            'body'      => 'Original body text',
        ], $overrides));
    }

    private function withOpenAiKey(User $user, string $key = 'sk-fake-test-key'): void
    {
        $user->setting()->updateOrCreate(['user_id' => $user->id], ['openai_api_key' => $key]);
    }

    // ── List ─────────────────────────────────────────────────────────────────

    public function test_list_pending_drafts(): void
    {
        $user = $this->actingAsUser();
        $this->makeDraft($user);

        $this->getJson('/api/app/v1/drafts')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_list_filters_by_status(): void
    {
        $user = $this->actingAsUser();
        $this->makeDraft($user, ['status' => 'draft']);
        $this->makeDraft($user, ['status' => 'approved']);
        $this->makeDraft($user, ['status' => 'rejected']);

        $this->getJson('/api/app/v1/drafts?status=approved')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/app/v1/drafts?status=rejected')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/app/v1/drafts?status=pending')->assertOk()->assertJsonPath('meta.total', 1);
    }

    // ── Generate ─────────────────────────────────────────────────────────────

    public function test_generate_creates_ai_draft(): void
    {
        $user = $this->actingAsUser();
        $this->fakeOpenAi('Postdoc Application', 'Dear Dr. Singh, I am writing to express...');
        $this->withOpenAiKey($user);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'c@test.com', 'status' => 'active']);
        $opp     = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

        $res = $this->postJson('/api/app/v1/drafts/generate', [
            'opportunity_id' => $opp->id,
            'contact_id'     => $contact->id,
            'tone'           => 'academic',
            'context'        => 'mention NLP background',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('data.ai_generated', true)
            ->assertJsonPath('data.subject', 'Postdoc Application')
            ->assertJsonPath('data.status', 'pending');

        Http::assertSent(fn ($req) => str_contains((string) $req->url(), 'openai.com'));
        $this->assertDatabaseHas('email_messages', ['ai_generated' => true, 'status' => 'draft']);
    }

    public function test_generate_errors_without_openai_key(): void
    {
        $user    = $this->actingAsUser();
        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'c@test.com', 'status' => 'active']);
        $opp     = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

        config(['services.openai.key' => null]);

        $this->postJson('/api/app/v1/drafts/generate', [
            'opportunity_id' => $opp->id,
            'contact_id'     => $contact->id,
        ])->assertStatus(422)->assertJsonPath('code', 'NO_OPENAI_KEY');
    }

    public function test_generate_blocked_for_suppressed_contact(): void
    {
        $user = $this->actingAsUser();
        $this->withOpenAiKey($user);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 's@test.com', 'status' => 'suppressed']);
        $opp     = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);

        $this->postJson('/api/app/v1/drafts/generate', [
            'opportunity_id' => $opp->id,
            'contact_id'     => $contact->id,
        ])->assertStatus(422)->assertJsonPath('code', 'CONTACT_SUPPRESSED');
    }

    // ── Show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_body_and_rendered_body(): void
    {
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user, ['ai_generated' => true, 'body' => '**Bold text**']);

        $this->getJson("/api/app/v1/drafts/{$draft->id}")
            ->assertOk()
            ->assertJsonPath('data.ai_generated', true)
            ->assertJsonStructure(['data' => ['body', 'rendered_body', 'subject', 'to_email']]);
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_update_draft_subject_and_body(): void
    {
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user);

        $this->patchJson("/api/app/v1/drafts/{$draft->id}", [
            'subject' => 'Updated Subject',
            'body'    => 'Updated body content',
        ])->assertOk()->assertJsonPath('data.subject', 'Updated Subject');
    }

    // ── Regenerate ───────────────────────────────────────────────────────────

    public function test_regenerate_updates_body_via_openai(): void
    {
        $user = $this->actingAsUser();
        $this->fakeOpenAi('New Subject', 'New AI body');
        $this->withOpenAiKey($user);

        $contact = Contact::factory()->create(['user_id' => $user->id, 'email' => 'c@test.com', 'status' => 'active']);
        $opp     = Opportunity::factory()->create(['user_id' => $user->id, 'status' => 'draft']);
        $draft   = $this->makeDraft($user, ['contact_id' => $contact->id, 'opportunity_id' => $opp->id]);

        $this->postJson("/api/app/v1/drafts/{$draft->id}/regenerate", ['tone' => 'warm'])
            ->assertOk()
            ->assertJsonPath('data.subject', 'New Subject')
            ->assertJsonPath('data.ai_generated', true);
    }

    // ── Lifecycle ────────────────────────────────────────────────────────────

    public function test_mark_ready_sets_approved(): void
    {
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user);

        $this->postJson("/api/app/v1/drafts/{$draft->id}/mark-ready")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('email_messages', ['id' => $draft->id, 'status' => 'approved']);
    }

    public function test_reject_sets_rejected(): void
    {
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user);

        $this->postJson("/api/app/v1/drafts/{$draft->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('email_messages', ['id' => $draft->id, 'status' => 'rejected']);
    }

    public function test_delete_draft(): void
    {
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user);

        $this->deleteJson("/api/app/v1/drafts/{$draft->id}")->assertOk();
        $this->getJson("/api/app/v1/drafts/{$draft->id}")->assertStatus(404);
    }

    // ── Send payload (no mail ever sent) ─────────────────────────────────────

    public function test_send_payload_returns_mailto_url_and_never_sends(): void
    {
        Mail::fake();
        $user  = $this->actingAsUser();
        $draft = $this->makeDraft($user, [
            'to_email' => 'recipient@example.com',
            'subject'  => 'Test Subject',
            'body'     => 'Hello world',
        ]);

        $res = $this->getJson("/api/app/v1/drafts/{$draft->id}/send-payload")
            ->assertOk()
            ->assertJsonStructure(['data' => ['to', 'subject', 'body_plain', 'body_html', 'mailto_url']]);

        $this->assertStringStartsWith('mailto:', $res->json('data.mailto_url'));
        $this->assertSame('recipient@example.com', $res->json('data.to'));

        Mail::assertNothingSent();
    }

    // ── Ownership isolation ───────────────────────────────────────────────────

    public function test_user_b_cannot_read_user_a_draft(): void
    {
        $userA = User::factory()->create();
        $draft = $this->makeDraft($userA);

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson("/api/app/v1/drafts/{$draft->id}")->assertStatus(404);
    }

    public function test_user_b_cannot_get_send_payload_of_user_a_draft(): void
    {
        $userA = User::factory()->create();
        $draft = $this->makeDraft($userA);

        Sanctum::actingAs(User::factory()->create(), ['*']);

        $this->getJson("/api/app/v1/drafts/{$draft->id}/send-payload")->assertStatus(404);
    }
}
