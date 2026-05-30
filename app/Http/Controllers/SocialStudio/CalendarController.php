<?php

namespace App\Http\Controllers\SocialStudio;

use App\Http\Controllers\Controller;
use App\Models\SocialPost;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $scheduled = SocialPost::where('user_id', $user->id)
            ->whereIn('status', ['scheduled', 'approved', 'failed', 'published'])
            ->whereNotNull('scheduled_at')
            ->with(['targets.account.provider'])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($post) {
                $tz = $post->timezone_display ?: 'UTC';
                return [
                    'id'           => $post->id,
                    'title'        => $post->title_internal,
                    'status'       => $post->status,
                    // Serialize in the post's own timezone so the YYYY-MM-DD prefix reflects the intended local date
                    'scheduled_at' => $post->scheduled_at->copy()->setTimezone($tz)->format('Y-m-d\TH:i:s'),
                    'tz'           => $tz,
                    'post_type'    => $post->post_type,
                    'url'          => route('social-studio.posts.show', $post->id),
                ];
            });

        return view('social-studio.calendar', compact('scheduled'));
    }
}
