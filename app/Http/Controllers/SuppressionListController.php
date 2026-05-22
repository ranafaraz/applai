<?php

namespace App\Http\Controllers;

use App\Models\SuppressionList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuppressionListController extends Controller
{
    public function index(Request $request): View
    {
        $query = SuppressionList::where('user_id', $request->user()->id);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%");
            });
        }

        $entries = $query->orderByDesc('created_at')->paginate(30)->withQueryString();

        return view('suppression-list.index', compact('entries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email'  => 'required|email|max:255',
            'reason' => 'nullable|string|max:255',
            'notes'  => 'nullable|string|max:2000',
        ]);

        $data['email']   = strtolower(trim($data['email']));
        $data['user_id'] = $request->user()->id;

        SuppressionList::firstOrCreate(
            ['user_id' => $data['user_id'], 'email' => $data['email']],
            ['reason' => $data['reason'] ?? null, 'notes' => $data['notes'] ?? null]
        );

        return redirect()->route('suppression-list.index')
            ->with('success', 'Email added to suppression list.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $entry = SuppressionList::where('user_id', $request->user()->id)->findOrFail($id);

        $entry->delete();

        return redirect()->route('suppression-list.index')
            ->with('success', 'Email removed from suppression list.');
    }
}
