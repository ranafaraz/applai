<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Contact;
use App\Models\EmailMessage;
use App\Models\EmailSignature;
use App\Models\Opportunity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

/**
 * AI Drafts for the mobile app (§4.4). The backend generates email bodies
 * via OpenAI using the user's stored key. The mobile API has NO /send endpoint:
 * send-payload returns a mailto: URL only — the app opens the mail client.
 *
 * Status mapping (app → DB):
 *   pending  → draft
 *   approved → approved (new value set by mark-ready)
 *   rejected → rejected (new value set by reject)
 *   sent     → sent | scheduled
 */
class DraftController extends AppController
{
    private const TONE_INSTRUCTIONS = [
        'professional' => 'Write in a formal, professional tone suitable for job applications and business correspondence.',
        'warm'         => 'Write in a warm, personable tone that shows genuine interest and enthusiasm.',
        'concise'      => 'Write in a concise, direct tone. Keep the email brief and to the point.',
        'academic'     => 'Write in an academic tone appropriate for research positions, PhD applications, and scholarly correspondence.',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = EmailMessage::where('user_id', $request->user()->id)
            ->where('direction', 'outbound');

        match ($request->query('status', 'pending')) {
            'sent'     => $query->whereIn('status', ['sent', 'scheduled']),
            'rejected' => $query->where('status', 'rejected'),
            'approved' => $query->where('status', 'approved'),
            default    => $query->where('status', 'draft'),
        };

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->with(['contact', 'opportunity'])
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->paginated($paginator, fn (EmailMessage $d) => $this->shape($d));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        return $this->data($this->shape($draft, detailed: true));
    }

    /** POST /drafts/generate — calls OpenAI server-side, never sends automatically. */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'opportunity_id' => ['required', 'integer'],
            'contact_id'     => ['required', 'integer'],
            'tone'           => ['sometimes', 'string', Rule::in(['professional', 'warm', 'concise', 'academic'])],
            'context'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $user        = $request->user();
        $opportunity = Opportunity::forCurrentUser()->find($validated['opportunity_id']);
        $contact     = Contact::forCurrentUser()->find($validated['contact_id']);

        if (! $opportunity) {
            return $this->notFound('Opportunity not found.');
        }
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        if (in_array($contact->status, ['suppressed', 'bounced'], true)) {
            return $this->error(
                'Cannot generate draft for a suppressed or bounced contact.',
                'CONTACT_SUPPRESSED'
            );
        }

        $apiKey = $this->resolveOpenAiKey($user);
        if (! $apiKey) {
            return $this->error(
                'No OpenAI API key configured. Add one in Settings → AI.',
                'NO_OPENAI_KEY'
            );
        }

        $tone    = $validated['tone'] ?? 'professional';
        $context = $validated['context'] ?? '';

        [$subject, $body] = $this->callOpenAi($apiKey, $opportunity, $contact, $tone, $context);

        if ($subject === null) {
            return $this->error(
                'AI generation failed. Check your OpenAI key in Settings → AI or try again.',
                'AI_ERROR'
            );
        }

        $signature         = EmailSignature::where('user_id', $user->id)->where('is_default', true)->first();
        $renderedSignature = $signature?->renderHtml();

        $draft = EmailMessage::create([
            'user_id'            => $user->id,
            'tenant_id'          => $user->tenant_id,
            'contact_id'         => $contact->id,
            'opportunity_id'     => $opportunity->id,
            'email_signature_id' => $signature?->id,
            'rendered_signature' => $renderedSignature,
            'subject'            => $subject,
            'body'               => $body,
            'to_email'           => $contact->email,
            'to_name'            => trim("{$contact->first_name} {$contact->last_name}") ?: $contact->email,
            'status'             => 'draft',
            'direction'          => 'outbound',
            'ai_generated'       => true,
        ]);

        return response()->json(
            ['data' => $this->shape($draft->load(['contact', 'opportunity']), detailed: true)],
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        if (! in_array($draft->status, ['draft', 'approved'], true)) {
            return $this->error('Only draft or approved drafts can be edited.', 'NOT_EDITABLE');
        }

        $validated = $request->validate([
            'subject' => ['sometimes', 'string', 'max:500'],
            'body'    => ['sometimes', 'string', 'max:50000'],
        ]);

        if (array_key_exists('subject', $validated)) {
            $draft->subject = $validated['subject'];
        }
        if (array_key_exists('body', $validated)) {
            $draft->body = $validated['body'];
        }
        $draft->save();

        return $this->data($this->shape($draft->fresh(['contact', 'opportunity']), detailed: true));
    }

    /** POST /drafts/{id}/regenerate — calls OpenAI again with optional new tone/context. */
    public function regenerate(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        $validated = $request->validate([
            'tone'    => ['sometimes', 'string', Rule::in(['professional', 'warm', 'concise', 'academic'])],
            'context' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $apiKey = $this->resolveOpenAiKey($request->user());
        if (! $apiKey) {
            return $this->error('No OpenAI API key configured.', 'NO_OPENAI_KEY');
        }

        $opportunity = Opportunity::find($draft->opportunity_id);
        $contact     = Contact::find($draft->contact_id);

        if (! $opportunity || ! $contact) {
            return $this->error('Draft is missing linked opportunity or contact.', 'MISSING_LINK');
        }

        [$subject, $body] = $this->callOpenAi(
            $apiKey,
            $opportunity,
            $contact,
            $validated['tone'] ?? 'professional',
            $validated['context'] ?? ''
        );

        if ($subject === null) {
            return $this->error('AI generation failed. Try again.', 'AI_ERROR');
        }

        $draft->subject      = $subject;
        $draft->body         = $body;
        $draft->ai_generated = true;
        $draft->status       = 'draft';
        $draft->save();

        return $this->data($this->shape($draft->fresh(['contact', 'opportunity']), detailed: true));
    }

    /** POST /drafts/{id}/mark-ready — user approves the draft for sending. */
    public function markReady(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        $draft->status = 'approved';
        $draft->save();

        return $this->data($this->shape($draft));
    }

    /** POST /drafts/{id}/reject — user rejects the AI draft. */
    public function reject(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        $draft->status = 'rejected';
        $draft->save();

        return $this->data($this->shape($draft));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        $draft->delete();

        return response()->json(['data' => ['message' => 'Draft deleted.']]);
    }

    /**
     * GET /drafts/{id}/send-payload — clipboard + mailto: URL.
     * The mobile API NEVER sends email. No /send endpoint exists.
     * This returns everything the app needs to open the system mail client.
     */
    public function sendPayload(Request $request, int $id): JsonResponse
    {
        $draft = $this->findOwned($request, $id);
        if (! $draft) {
            return $this->notFound('Draft not found.');
        }

        $bodyHtml  = $draft->rendered_body ?? nl2br(e($draft->body ?? ''));
        $bodyPlain = strip_tags($bodyHtml);

        $mailto = 'mailto:' . rawurlencode((string) $draft->to_email)
            . '?subject=' . rawurlencode((string) $draft->subject)
            . '&body='    . rawurlencode($bodyPlain);

        return $this->data([
            'to'         => $draft->to_email,
            'subject'    => $draft->subject,
            'body_plain' => $bodyPlain,
            'body_html'  => $bodyHtml,
            'mailto_url' => $mailto,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function findOwned(Request $request, int $id): ?EmailMessage
    {
        return EmailMessage::where('user_id', $request->user()->id)
            ->where('direction', 'outbound')
            ->with(['contact', 'opportunity'])
            ->find($id);
    }

    private function resolveOpenAiKey(User $user): ?string
    {
        $perUserKey = $user->setting?->openai_api_key;
        if ($perUserKey) {
            return $perUserKey;
        }

        $envKey = config('services.openai.key');
        return $envKey ?: null;
    }

    private function callOpenAi(string $apiKey, Opportunity $opportunity, Contact $contact, string $tone, string $context): array
    {
        $toneInstruction = self::TONE_INSTRUCTIONS[$tone] ?? self::TONE_INSTRUCTIONS['professional'];
        $contactName     = trim("{$contact->first_name} {$contact->last_name}") ?: $contact->email;

        $systemPrompt = <<<PROMPT
You are an expert professional email writer helping a job seeker craft outreach emails.
{$toneInstruction}
Write ONLY the email body and subject — no headers (From/To/Date), no meta-commentary.
Return valid JSON with exactly two fields: "subject" (string) and "body" (string, markdown).
PROMPT;

        $userPrompt = "Write an outreach email for this {$opportunity->type} opportunity:\n"
            . "- Position/Title: {$opportunity->title}\n"
            . "- Organization: {$opportunity->organization}\n"
            . "- Recipient name: {$contactName}\n"
            . ($contact->job_title ? "- Recipient role: {$contact->job_title}\n" : '')
            . ($context ? "- Additional context from applicant: {$context}\n" : '');

        try {
            $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
                ->timeout(30)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model'           => 'gpt-4o-mini',
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user',   'content' => $userPrompt],
                    ],
                    'max_tokens'  => 800,
                    'temperature' => 0.7,
                ]);

            if (! $response->successful()) {
                return [null, null];
            }

            $parsed  = json_decode($response->json('choices.0.message.content') ?? '{}', true);
            $subject = $parsed['subject'] ?? null;
            $body    = $parsed['body'] ?? null;

            if (! $subject || ! $body) {
                return [null, null];
            }

            return [$subject, $body];
        } catch (\Throwable) {
            return [null, null];
        }
    }

    private function shape(EmailMessage $draft, bool $detailed = false): array
    {
        $appStatus = match ($draft->status) {
            'sent', 'scheduled' => 'sent',
            'rejected'          => 'rejected',
            'approved'          => 'approved',
            default             => 'pending',
        };

        $base = [
            'id'             => $draft->id,
            'status'         => $appStatus,
            'ai_generated'   => (bool) $draft->ai_generated,
            'subject'        => $draft->subject,
            'to_email'       => $draft->to_email,
            'to_name'        => $draft->to_name,
            'contact_id'     => $draft->contact_id,
            'opportunity_id' => $draft->opportunity_id,
            'preview'        => mb_substr(strip_tags($draft->body ?? ''), 0, 200),
            'created_at'     => $draft->created_at?->toISOString(),
            'updated_at'     => $draft->updated_at?->toISOString(),
        ];

        if ($detailed) {
            $base['body']          = $draft->body;
            $base['rendered_body'] = $draft->rendered_body;
        }

        return $base;
    }
}
