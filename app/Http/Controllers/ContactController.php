<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Lookup;
use App\Models\Opportunity;
use App\Models\SuppressionList;
use App\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->tenantQuery(Contact::class)->with('tags');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($tagId = $request->input('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        $contacts = $query->orderByDesc('created_at')->paginate(30)->withQueryString();
        $tags     = $this->tenantQuery(Tag::class)->orderBy('name')->get();

        return view('contacts.index', compact('contacts', 'tags'));
    }

    public function create(): View
    {
        $tags = $this->tenantQuery(Tag::class)->orderBy('name')->get();
        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'organization', 'status']);

        return view('contacts.create', compact('tags', 'opportunities'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data           = $request->validated();
        $tagIds         = $data['tags'] ?? [];
        $opportunityIds = $data['opportunities'] ?? [];
        unset($data['tags'], $data['opportunities']);

        $contact = Contact::create($this->tenantData($data));

        if ($tagIds) {
            $contact->tags()->sync($tagIds);
        }
        if ($opportunityIds) {
            $contact->opportunities()->sync($opportunityIds);
        }

        $this->recordLookups($contact, $request->user()->tenant_id);

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $contact = $this->tenantQuery(Contact::class)
            ->with(['tags', 'opportunities', 'emailMessages.emailAccount'])
            ->findOrFail($id);

        $this->authorize('view', $contact);

        $events  = $contact->timelineEvents()->orderByDesc('happened_at')->get();
        $opportunities = $contact->opportunities;
        $emails  = $contact->emailMessages()->orderByDesc('created_at')->get();

        return view('contacts.show', compact('contact', 'events', 'opportunities', 'emails'));
    }

    public function edit(Request $request, int $id): View
    {
        $contact = $this->tenantQuery(Contact::class)->with(['tags', 'opportunities'])->findOrFail($id);
        $this->authorize('update', $contact);

        $tags = $this->tenantQuery(Tag::class)->orderBy('name')->get();
        $opportunities = $this->tenantQuery(Opportunity::class)
            ->orderByDesc('updated_at')
            ->get(['id', 'title', 'organization', 'status']);

        return view('contacts.edit', compact('contact', 'tags', 'opportunities'));
    }

    public function update(UpdateContactRequest $request, int $id): RedirectResponse
    {
        $contact = $this->tenantQuery(Contact::class)->findOrFail($id);
        $this->authorize('update', $contact);

        $data           = $request->validated();
        $tagIds         = $data['tags'] ?? [];
        $opportunityIds = $data['opportunities'] ?? [];
        unset($data['tags'], $data['opportunities']);

        $contact->update($data);
        $contact->tags()->sync($tagIds);
        $contact->opportunities()->sync($opportunityIds);

        $this->recordLookups($contact, $request->user()->tenant_id);

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact updated successfully.');
    }

    /** Write free-form lookup values back so future autocompletes include them. */
    private function recordLookups(Contact $contact, ?int $tenantId): void
    {
        Lookup::record('country',  $contact->country,  $tenantId);
        Lookup::record('city',     $contact->city,     $tenantId);
        Lookup::record('industry', $contact->industry, $tenantId);
        Lookup::record('source',   $contact->source,   $tenantId);
        Lookup::record('designation', $contact->job_title, $tenantId);
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $contact = $this->tenantQuery(Contact::class)->findOrFail($id);
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contact deleted.');
    }

    /**
     * Lightweight JSON endpoint to create a contact inline from an
     * opportunity form modal. Returns the new contact's id + display fields.
     */
    public function quickStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'nullable|string|max:255',
            'email'      => 'required|email|max:255',
            'company'    => 'nullable|string|max:255',
            'job_title'  => 'nullable|string|max:255',
            'phone'      => 'nullable|string|max:50',
        ]);

        $existing = $this->tenantQuery(Contact::class)
            ->where('email', strtolower($data['email']))
            ->first();
        if ($existing) {
            return response()->json([
                'id'       => $existing->id,
                'label'    => trim($existing->first_name . ' ' . $existing->last_name) ?: $existing->email,
                'sublabel' => $existing->email . ($existing->company ? ' · ' . $existing->company : ''),
                'created'  => false,
            ]);
        }

        $data['email']  = strtolower($data['email']);
        $data['status'] = 'active';
        $data['source'] = $data['source'] ?? 'inline_modal';
        $contact = Contact::create($this->tenantData($data));

        // Record city/industry/source values into lookups for future autocomplete
        $tenantId = $request->user()->tenant_id;
        Lookup::record('city',     $contact->city,     $tenantId);
        Lookup::record('industry', $contact->industry, $tenantId);
        Lookup::record('source',   $contact->source,   $tenantId);

        return response()->json([
            'id'       => $contact->id,
            'label'    => trim($contact->first_name . ' ' . $contact->last_name) ?: $contact->email,
            'sublabel' => $contact->email . ($contact->company ? ' · ' . $contact->company : ''),
            'created'  => true,
        ], 201);
    }

    public function suppress(Request $request, int $id): RedirectResponse
    {
        $contact = $this->tenantQuery(Contact::class)->findOrFail($id);
        $this->authorize('update', $contact);

        SuppressionList::firstOrCreate(
            array_merge($this->tenantScope(), ['email' => strtolower($contact->email)]),
            ['user_id' => auth()->id(), 'reason' => 'manual', 'notes' => 'Suppressed from contact record.']
        );

        $contact->update(['status' => 'suppressed']);

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact suppressed and added to suppression list.');
    }
}
