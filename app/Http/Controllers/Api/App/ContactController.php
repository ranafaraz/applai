<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Support\OpportunityStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Contacts for the mobile app (§4.3). Scoped to the authenticated user via
 * Tenantable forCurrentUser(). Maps CRM columns to the app's field names:
 *   job_title  ↔  role
 *   company    ↔  org
 * Computed fields: initials (first letter of each name part), avatar_color
 * (deterministic crc32-based pick from a fixed palette).
 */
class ContactController extends AppController
{
    private const AVATAR_COLORS = [
        '#6366F1', '#8B5CF6', '#EC4899', '#EF4444', '#F59E0B',
        '#10B981', '#3B82F6', '#14B8A6', '#F97316', '#84CC16',
    ];

    public function index(Request $request): JsonResponse
    {
        $query = Contact::forCurrentUser();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('first_name', 'like', "%{$q}%")
                    ->orWhere('last_name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('company', 'like', "%{$q}%");
            });
        }

        if ($role = $request->query('role')) {
            $query->where('job_title', 'like', "%{$role}%");
        }

        if ($request->has('suppressed')) {
            $request->boolean('suppressed')
                ? $query->whereIn('status', ['suppressed', 'bounced'])
                : $query->where('status', 'active');
        }

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->with(['tags'])->paginate($perPage);

        return $this->paginated($paginator, fn (Contact $c) => $this->shape($c));
    }

    public function show(int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->with(['tags'])->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        return $this->data($this->shape($contact, detailed: true));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, creating: true);

        [$firstName, $lastName] = $this->splitFullName($validated['full_name']);

        $contact             = new Contact();
        $contact->user_id    = $request->user()->id;
        $contact->first_name = $firstName;
        $contact->last_name  = $lastName;
        $contact->status     = 'active';
        $this->fill($contact, $validated);
        $contact->save();

        $this->syncTags($contact, $validated['tags'] ?? null, $request);

        return response()->json(['data' => $this->shape($contact->fresh(['tags']), detailed: true)], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $validated = $this->validatePayload($request, creating: false);

        if (isset($validated['full_name'])) {
            [$firstName, $lastName]  = $this->splitFullName($validated['full_name']);
            $contact->first_name     = $firstName;
            $contact->last_name      = $lastName;
        }

        $this->fill($contact, $validated);
        $contact->save();

        if (array_key_exists('tags', $validated)) {
            $this->syncTags($contact, $validated['tags'], $request);
        }

        return $this->data($this->shape($contact->fresh(['tags']), detailed: true));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $request->boolean('hard') ? $contact->forceDelete() : $contact->delete();

        return response()->json(['data' => ['message' => 'Contact deleted.']]);
    }

    /** POST /contacts/{id}/suppress — sets status=suppressed or bounced. */
    public function suppress(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['required', 'string', 'in:bounced,unsubscribed,not_relevant'],
        ]);

        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $contact->status = $request->input('reason') === 'bounced' ? 'bounced' : 'suppressed';
        $contact->save();

        return $this->data($this->shape($contact));
    }

    /** DELETE /contacts/{id}/suppress — restores status=active. */
    public function unsuppress(int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $contact->status = 'active';
        $contact->save();

        return $this->data($this->shape($contact));
    }

    /** GET /contacts/{id}/opportunities — linked opportunities (Contact Detail tab). */
    public function opportunities(int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $opps = $contact->opportunities()->get()->map(fn (Opportunity $o) => [
            'id'       => $o->id,
            'type'     => $o->type,
            'title'    => $o->title,
            'org'      => $o->organization,
            'stage'    => OpportunityStage::normalize($o->status),
            'deadline' => $o->deadline?->toDateString(),
            'role'     => $o->pivot->role ?? null,
        ]);

        return response()->json(['data' => $opps]);
    }

    /** GET /contacts/{id}/emails — outreach history. */
    public function emails(int $id): JsonResponse
    {
        $contact = Contact::forCurrentUser()->find($id);
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $emails = $contact->emailMessages()->orderBy('created_at', 'desc')->get()->map(fn ($m) => [
            'id'         => $m->id,
            'subject'    => $m->subject,
            'status'     => $m->status,
            'direction'  => $m->direction ?? 'outbound',
            'is_draft'   => $m->status === 'draft',
            'sent_at'    => $m->sent_at?->toISOString(),
            'created_at' => $m->created_at?->toISOString(),
        ]);

        return response()->json(['data' => $emails]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'full_name'    => [$required, 'string', 'max:255'],
            'email'        => [$required, 'email', 'max:255'],
            'role'         => ['sometimes', 'nullable', 'string', 'max:255'],
            'org'          => ['sometimes', 'nullable', 'string', 'max:255'],
            'linkedin_url' => ['sometimes', 'nullable', 'url', 'max:500'],
            'website'      => ['sometimes', 'nullable', 'url', 'max:500'],
            'notes'        => ['sometimes', 'nullable', 'string'],
            'tags'         => ['sometimes', 'nullable', 'array'],
            'tags.*'       => ['string', 'max:50'],
            'twitter'      => ['sometimes', 'nullable', 'string', 'max:255'],
            'photo_url'    => ['sometimes', 'nullable', 'url', 'max:500'],
        ]);
    }

    private function fill(Contact $contact, array $v): void
    {
        if (array_key_exists('email', $v)) {
            $contact->email = $v['email'];
        }
        if (array_key_exists('role', $v)) {
            $contact->job_title = $v['role'];
        }
        if (array_key_exists('org', $v)) {
            $contact->company = $v['org'];
        }
        if (array_key_exists('linkedin_url', $v)) {
            $contact->linkedin_url = $v['linkedin_url'];
        }
        if (array_key_exists('website', $v)) {
            $contact->website = $v['website'];
        }
        if (array_key_exists('notes', $v)) {
            $contact->notes = $v['notes'];
        }
    }

    private function splitFullName(string $fullName): array
    {
        $parts = explode(' ', trim($fullName), 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function syncTags(Contact $contact, ?array $names, Request $request): void
    {
        if ($names === null) {
            return;
        }

        $ids = [];
        foreach (array_unique($names) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $tag = Tag::forCurrentUser()->where('name', $name)->first()
                ?? Tag::create([
                    'user_id' => $request->user()->id,
                    'name'    => $name,
                    'slug'    => Str::slug($name),
                ]);
            $ids[] = $tag->id;
        }

        $contact->tags()->sync($ids);
    }

    private function shape(Contact $contact, bool $detailed = false): array
    {
        $base = [
            'id'           => $contact->id,
            'full_name'    => trim("{$contact->first_name} {$contact->last_name}") ?: $contact->email,
            'initials'     => $this->initials($contact),
            'avatar_color' => $this->avatarColor($contact),
            'email'        => $contact->email,
            'role'         => $contact->job_title,
            'org'          => $contact->company,
            'status'       => $contact->status ?? 'active',
            'linkedin_url' => $contact->linkedin_url,
            'website'      => $contact->website,
            'tags'         => $contact->tags?->pluck('name')->values() ?? [],
            'created_at'   => $contact->created_at?->toISOString(),
            'updated_at'   => $contact->updated_at?->toISOString(),
        ];

        if ($detailed) {
            $base['notes']             = $contact->notes;
            $base['last_contacted_at'] = $contact->last_contacted_at?->toISOString();
        }

        return $base;
    }

    private function initials(Contact $contact): string
    {
        $first = mb_strtoupper(mb_substr($contact->first_name ?? '', 0, 1));
        $last  = mb_strtoupper(mb_substr($contact->last_name ?? '', 0, 1));

        if ($first === '' && $last === '') {
            return mb_strtoupper(mb_substr($contact->email ?? '?', 0, 2));
        }

        return $first . $last;
    }

    private function avatarColor(Contact $contact): string
    {
        $seed  = abs(crc32($contact->email ?? (string) $contact->id));
        $index = $seed % count(self::AVATAR_COLORS);

        return self::AVATAR_COLORS[$index];
    }
}
