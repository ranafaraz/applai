<?php

namespace App\Http\Controllers\Api\App;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\Tag;
use App\Support\OpportunityStage;
use App\Support\OpportunityType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Opportunities for the mobile app (§4.2). Every query is scoped to the
 * authenticated user via the Tenantable `forCurrentUser` scope. Field names map
 * to the prototype's data model: org↔organization, stage↔status (mapped via
 * OpportunityStage). Type/deadline-chip colors are derived client-side and are
 * never sent.
 */
class OpportunityController extends AppController
{
    public function index(Request $request): JsonResponse
    {
        $query = Opportunity::forCurrentUser();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($sub) use ($q) {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('organization', 'like', "%{$q}%");
            });
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($stage = $request->query('stage')) {
            $query->whereIn('status', OpportunityStage::storedValuesFor($stage));
        }

        switch ($request->query('deadline')) {
            case 'overdue':
                $query->whereNotNull('deadline')->whereDate('deadline', '<', Carbon::today());
                break;
            case 'this_week':
                $query->whereNotNull('deadline')
                    ->whereBetween('deadline', [Carbon::today(), Carbon::today()->endOfWeek()]);
                break;
            case 'this_month':
                $query->whereNotNull('deadline')
                    ->whereBetween('deadline', [Carbon::today(), Carbon::today()->endOfMonth()]);
                break;
        }

        if ($request->boolean('has_draft')) {
            $query->whereHas('emailMessages', fn ($m) => $m->where('status', 'draft'));
        }
        if ($request->boolean('has_contact')) {
            $query->has('contacts');
        }
        if ($tag = $request->query('tag')) {
            $query->whereHas('tags', fn ($t) => $t->where('name', $tag)->orWhere('tags.id', $tag));
        }

        match ($request->query('sort', 'updated')) {
            'deadline' => $query->orderByRaw('deadline IS NULL, deadline asc'),
            'added'    => $query->orderBy('created_at', 'desc'),
            'alpha'    => $query->orderBy('title', 'asc'),
            default    => $query->orderBy('updated_at', 'desc'),
        };

        $perPage   = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->with(['contacts', 'tags'])->paginate($perPage);

        return $this->paginated($paginator, fn (Opportunity $o) => $this->shape($o));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->with(['contacts', 'tags'])->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        return $this->data($this->shape($opp, detailed: true));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request, creating: true);

        $opp = new Opportunity();
        $opp->user_id = $request->user()->id;
        $this->fill($opp, $validated);
        $opp->save();

        $this->syncContact($opp, $validated['contact_id'] ?? null);
        $this->syncTags($opp, $validated['tags'] ?? null, $request);

        $warnings = $this->deadlineWarnings($opp);

        return response()->json(
            array_merge(['data' => $this->shape($opp->fresh(['contacts', 'tags']), detailed: true)], $warnings),
            201
        );
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $validated = $this->validatePayload($request, creating: false);
        $this->fill($opp, $validated);
        $opp->save();

        if (array_key_exists('contact_id', $validated)) {
            $this->syncContact($opp, $validated['contact_id']);
        }
        if (array_key_exists('tags', $validated)) {
            $this->syncTags($opp, $validated['tags'], $request);
        }

        $warnings = $this->deadlineWarnings($opp);

        return response()->json(
            array_merge(['data' => $this->shape($opp->fresh(['contacts', 'tags']), detailed: true)], $warnings)
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $request->boolean('hard') ? $opp->forceDelete() : $opp->delete();

        return response()->json(['data' => ['message' => 'Opportunity deleted.']]);
    }

    /** PATCH /opportunities/{id}/stage — kanban drag + list swipe. */
    public function changeStage(Request $request, int $id): JsonResponse
    {
        $request->validate(['stage' => ['required', 'string', Rule::in(OpportunityStage::STAGES)]]);

        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $opp->status = $request->string('stage');
        $opp->last_activity_at = now();
        $opp->save();

        return $this->data($this->shape($opp->fresh(['contacts', 'tags']), detailed: true));
    }

    /** GET /opportunities/{id}/emails — Detail "Emails" tab. */
    public function emails(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $emails = $opp->emailMessages()->orderBy('created_at', 'desc')->get()->map(fn ($m) => [
            'id'         => $m->id,
            'subject'    => $m->subject,
            'to_email'   => $m->to_email,
            'status'     => $m->status,
            'direction'  => $m->direction,
            'is_draft'   => $m->status === 'draft',
            'sent_at'    => $m->sent_at?->toISOString(),
            'created_at' => $m->created_at?->toISOString(),
        ]);

        return response()->json(['data' => $emails]);
    }

    /** GET /opportunities/{id}/timeline — Detail "Timeline" tab. */
    public function timeline(Request $request, int $id): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $events = $opp->timelineEvents()->orderBy('happened_at', 'desc')->get()->map(fn ($e) => [
            'id'          => $e->id,
            'event_type'  => $e->event_type,
            'description' => $e->description,
            'metadata'    => $e->metadata,
            'happened_at' => $e->happened_at?->toISOString(),
        ]);

        return response()->json(['data' => $events]);
    }

    /** POST /opportunities/{id}/contact — link a contact. */
    public function linkContact(Request $request, int $id): JsonResponse
    {
        $request->validate(['contact_id' => ['required', 'integer']]);

        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $contact = Contact::forCurrentUser()->find($request->integer('contact_id'));
        if (! $contact) {
            return $this->notFound('Contact not found.');
        }

        $opp->contacts()->syncWithoutDetaching([$contact->id]);

        return $this->data($this->shape($opp->fresh(['contacts', 'tags']), detailed: true));
    }

    /** DELETE /opportunities/{id}/contact/{contactId} — unlink a contact. */
    public function unlinkContact(Request $request, int $id, int $contactId): JsonResponse
    {
        $opp = Opportunity::forCurrentUser()->find($id);
        if (! $opp) {
            return $this->notFound('Opportunity not found.');
        }

        $opp->contacts()->detach($contactId);

        return $this->data($this->shape($opp->fresh(['contacts', 'tags']), detailed: true));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function validatePayload(Request $request, bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'title'                => [$required, 'string', 'max:500'],
            'org'                  => [$required, 'string', 'max:255'],
            'type'                 => ['sometimes', 'string', Rule::in(OpportunityType::allowed())],
            'stage'                => ['sometimes', 'string', Rule::in(OpportunityStage::STAGES)],
            'deadline'             => ['sometimes', 'nullable', 'date'],
            'url'                  => ['sometimes', 'nullable', 'url'],
            'notes'                => ['sometimes', 'nullable', 'string'],
            'priority'             => ['sometimes', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'contact_id'           => ['sometimes', 'nullable', 'integer'],
            'tags'                 => ['sometimes', 'nullable', 'array'],
            'tags.*'               => ['string', 'max:50'],
            // Accepted for forward-compatibility; wired in later milestones.
            'next_followup_at'     => ['sometimes', 'nullable', 'date'],
            'reminder_offset_days' => ['sometimes', 'nullable', 'integer'],
            'request_ai_draft'     => ['sometimes', 'boolean'],
            'draft_context'        => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
    }

    private function fill(Opportunity $opp, array $v): void
    {
        if (array_key_exists('title', $v)) {
            $opp->title = $v['title'];
        }
        if (array_key_exists('org', $v)) {
            $opp->organization = $v['org'];
        }
        if (array_key_exists('type', $v)) {
            $opp->type = $v['type'];
        }
        if (array_key_exists('stage', $v)) {
            $opp->status = $v['stage'];
        } elseif (! $opp->exists && ! $opp->status) {
            $opp->status = 'draft';
        }
        if (array_key_exists('deadline', $v)) {
            $opp->deadline = $v['deadline'];
        }
        if (array_key_exists('url', $v)) {
            $opp->url = $v['url'];
        }
        if (array_key_exists('notes', $v)) {
            $opp->notes = $v['notes'];
        }
        if (array_key_exists('priority', $v)) {
            $opp->priority = $v['priority'];
        }
    }

    private function syncContact(Opportunity $opp, ?int $contactId): void
    {
        if ($contactId === null) {
            return;
        }
        $contact = Contact::forCurrentUser()->find($contactId);
        if ($contact) {
            $opp->contacts()->syncWithoutDetaching([$contact->id]);
        }
    }

    /** @param array<int,string>|null $names */
    private function syncTags(Opportunity $opp, ?array $names, Request $request): void
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

        $opp->tags()->sync($ids);
    }

    private function deadlineWarnings(Opportunity $opp): array
    {
        if ($opp->deadline && $opp->deadline->isPast()) {
            return ['warnings' => ['The deadline is in the past.']];
        }

        return [];
    }

    private function shape(Opportunity $opp, bool $detailed = false): array
    {
        $base = [
            'id'               => $opp->id,
            'type'             => $opp->type,
            'title'            => $opp->title,
            'org'              => $opp->organization,
            'stage'            => OpportunityStage::normalize($opp->status),
            'priority'         => $opp->priority,
            'deadline'         => $opp->deadline?->toDateString(),
            'url'              => $opp->url,
            'has_contact'      => $opp->contacts->isNotEmpty(),
            'tags'             => $opp->tags->pluck('name')->values(),
            'last_activity_at' => $opp->last_activity_at?->toISOString(),
            'created_at'       => $opp->created_at?->toISOString(),
            'updated_at'       => $opp->updated_at?->toISOString(),
        ];

        if ($detailed) {
            $base['notes']    = $opp->notes;
            $base['contacts'] = $opp->contacts->map(fn (Contact $c) => [
                'id'        => $c->id,
                'full_name' => trim("{$c->first_name} {$c->last_name}"),
                'email'     => $c->email,
                'role'      => $c->pivot->role ?? null,
            ])->values();
        }

        return $base;
    }
}
