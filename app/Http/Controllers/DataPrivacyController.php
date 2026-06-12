<?php

namespace App\Http\Controllers;

use App\Jobs\ExportTenantDataJob;
use App\Services\TenantDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Export-anytime and account deletion (GDPR). Export is available on every
 * plan — data portability is a trust feature, not an upsell.
 */
class DataPrivacyController extends Controller
{
    /** Queue a full tenant export; the user is notified when it's ready. */
    public function requestExport(Request $request): RedirectResponse
    {
        ExportTenantDataJob::dispatch($request->user()->id);

        return back()->with('success', 'Your export is being prepared. You will receive a notification with a download link shortly.');
    }

    /** Download a finished export (temporary signed URL + tenant ownership check). */
    public function download(Request $request, string $file): BinaryFileResponse
    {
        $user = $request->user();

        // Export files are named tenant-{id}-... — only the owning tenant may download.
        abort_unless(
            $user->tenant_id && str_starts_with($file, 'tenant-' . $user->tenant_id . '-'),
            403,
        );

        $relative = TenantDataService::EXPORT_DIR . '/' . basename($file);

        abort_unless(Storage::disk(TenantDataService::EXPORT_DISK)->exists($relative), 404);

        return response()->download(
            Storage::disk(TenantDataService::EXPORT_DISK)->path($relative),
            'crm-data-export.zip',
        );
    }

    /**
     * Request account deletion: re-authenticates, marks the tenant
     * cancelled, and schedules the hard purge after a 30-day grace window
     * (tenants:purge-deleted).
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate(['password' => 'required|string']);

        $user   = $request->user();
        $tenant = $user->tenant;

        if (! Hash::check($request->input('password'), $user->password)) {
            return back()->withErrors(['password' => 'The password is incorrect.']);
        }

        if (! $user->isAdmin()) {
            return back()->withErrors(['password' => 'Only workspace admins can delete the account.']);
        }

        $tenant->update([
            'status'                => 'cancelled',
            'deletion_requested_at' => now(),
        ]);

        activity()->causedBy($user)->performedOn($tenant)
            ->log('Account deletion requested; data will be permanently removed after 30 days.');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('success', 'Your account is scheduled for deletion. All data will be permanently removed after 30 days.');
    }
}
