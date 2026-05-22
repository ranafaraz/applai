<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\UpdateContactRequest;
use App\Models\Contact;
use App\Models\SuppressionList;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(Request $request): View
    {
        $query = Contact::where('user_id', $request->user()->id)
            ->with('tags');

        // Search
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by tag
        if ($tagId = $request->input('tag')) {
            $query->whereHas('tags', fn ($q) => $q->where('tags.id', $tagId));
        }

        $contacts = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        $tags = Tag::where('user_id', $request->user()->id)->orderBy('name')->get();

        return view('contacts.index', compact('contacts', 'tags'));
    }

    public function create(): View
    {
        return view('contacts.create');
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        unset($data['tags']);

        $data['user_id'] = $request->user()->id;

        $contact = Contact::create($data);

        if ($tagIds) {
            $contact->tags()->sync($tagIds);
        }

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact created successfully.');
    }

    public function show(Request $request, int $id): View
    {
        $contact = Contact::where('user_id', $request->user()->id)
            ->with(['tags', 'opportunities', 'emailMessages.emailAccount'])
            ->findOrFail($id);

        $this->authorize('view', $contact);

        $events = $contact->timelineEvents()->orderByDesc('happened_at')->get();
        $opportunities = $contact->opportunities;
        $emails = $contact->emailMessages()->orderByDesc('created_at')->get();
        $timeline = $events;

        return view('contacts.show', compact('contact', 'events', 'opportunities', 'emails', 'timeline'));
    }

    public function edit(Request $request, int $id): View
    {
        $contact = Contact::where('user_id', $request->user()->id)
            ->with('tags')
            ->findOrFail($id);

        $this->authorize('update', $contact);

        $tags = Tag::where('user_id', $request->user()->id)->orderBy('name')->get();

        return view('contacts.edit', compact('contact', 'tags'));
    }

    public function update(UpdateContactRequest $request, int $id): RedirectResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);

        $this->authorize('update', $contact);

        $data = $request->validated();
        $tagIds = $data['tags'] ?? [];
        unset($data['tags']);

        $contact->update($data);
        $contact->tags()->sync($tagIds);

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);

        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Contact deleted.');
    }

    public function suppress(Request $request, int $id): RedirectResponse
    {
        $contact = Contact::where('user_id', $request->user()->id)->findOrFail($id);

        $this->authorize('update', $contact);

        // Add to suppression list if not already there
        SuppressionList::firstOrCreate(
            ['user_id' => $request->user()->id, 'email' => strtolower($contact->email)],
            ['reason' => 'manual', 'notes' => 'Suppressed from contact record.']
        );

        $contact->update(['status' => 'suppressed']);

        return redirect()->route('contacts.show', $contact->id)
            ->with('success', 'Contact suppressed and added to suppression list.');
    }
}
