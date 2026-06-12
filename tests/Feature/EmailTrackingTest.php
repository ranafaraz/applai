<?php

namespace Tests\Feature;

use App\Models\EmailAccount;
use App\Models\EmailMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\EmailTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function message(string $plan = 'pro'): EmailMessage
    {
        $tenant = Tenant::factory()->create(['plan' => $plan]);
        $user   = User::factory()->create(['tenant_id' => $tenant->id]);
        $account = EmailAccount::factory()->create(['user_id' => $user->id]);

        return EmailMessage::factory()->create([
            'user_id'          => $user->id,
            'tenant_id'        => $tenant->id,
            'email_account_id' => $account->id,
            'status'           => 'sent',
            'sent_at'          => now()->subHour(),
            'body'             => '<p>Hi! <a href="https://example.com/job">Apply here</a></p>',
        ]);
    }

    public function test_prepare_html_injects_pixel_and_rewrites_links_for_paid_plan(): void
    {
        $message = $this->message('pro');

        $html = app(EmailTrackingService::class)->prepareHtml($message, $message->body);

        $this->assertStringContainsString('/t/o/' . $message->id, $html);
        $this->assertStringContainsString('/t/c/' . $message->id, $html);
        $this->assertStringNotContainsString('href="https://example.com/job"', $html);
    }

    public function test_prepare_html_is_a_noop_on_free_plan(): void
    {
        $message = $this->message('free');

        $html = app(EmailTrackingService::class)->prepareHtml($message, $message->body);

        $this->assertSame($message->body, $html);
    }

    public function test_open_pixel_records_open_once(): void
    {
        $message = $this->message();
        $pixel   = URL::signedRoute('track.open', ['message' => $message->id]);

        $this->get($pixel)->assertOk()->assertHeader('Content-Type', 'image/gif');
        $this->get($pixel)->assertOk();

        $message->refresh();
        $this->assertNotNull($message->opened_at);
        $this->assertSame(2, $message->open_count);
    }

    public function test_open_pixel_rejects_unsigned_requests(): void
    {
        $message = $this->message();

        $this->get('/t/o/' . $message->id)->assertForbidden();

        $this->assertNull($message->fresh()->opened_at);
    }

    public function test_click_records_and_redirects(): void
    {
        $message = $this->message();
        $link    = URL::signedRoute('track.click', [
            'message' => $message->id,
            'url'     => 'https://example.com/job',
        ]);

        $this->get($link)->assertRedirect('https://example.com/job');

        $message->refresh();
        $this->assertNotNull($message->clicked_at);
        $this->assertNotNull($message->opened_at, 'A click should imply an open.');
        $this->assertSame(1, $message->click_count);
        $this->assertDatabaseHas('email_link_clicks', [
            'email_message_id' => $message->id,
            'url'              => 'https://example.com/job',
        ]);
    }

    public function test_scanner_user_agent_does_not_count_as_open(): void
    {
        $message = $this->message();
        $pixel   = URL::signedRoute('track.open', ['message' => $message->id]);

        $this->get($pixel, ['User-Agent' => 'Barracuda Sentinel scanner'])->assertOk();

        $message->refresh();
        $this->assertNull($message->opened_at);
        $this->assertSame(0, $message->open_count);
    }

    public function test_hit_within_seconds_of_send_does_not_count(): void
    {
        $message = $this->message();
        $message->update(['sent_at' => now()]);

        $pixel = URL::signedRoute('track.open', ['message' => $message->id]);
        $this->get($pixel)->assertOk();

        $this->assertNull($message->fresh()->opened_at);
    }
}
