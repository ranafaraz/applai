<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $query = Activity::where('causer_id', $request->user()->id)
            ->where('causer_type', \App\Models\User::class);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        if ($subjectType = $request->input('subject_type')) {
            $query->where('subject_type', $subjectType);
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        return view('audit-logs.index', compact('logs'));
    }
}
