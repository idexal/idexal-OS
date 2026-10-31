<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard.notifications.index', [
            'notifications' => $request->user()
                ->appNotifications()
                ->latest('created_at')
                ->paginate(25),
        ]);
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        $request->user()->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('status', __('ui.common.saved'));
    }

    public function markRead(Request $request, Notification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->getKey(), 404);

        if (! $notification->isRead()) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        if (is_string($notification->url) && $notification->url !== '') {
            return redirect()->to($notification->url);
        }

        return back();
    }
}
