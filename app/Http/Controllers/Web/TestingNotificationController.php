<?php

namespace App\Http\Controllers\Web;

use App\Models\SystemNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TestingNotificationController extends TestingBaseController
{
    public function index(): View|RedirectResponse
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        return view('testing.notifications.index', [
            'notifications' => SystemNotification::where('user_id', Auth::id())->latest()->get(),
        ]);
    }

    public function markAsRead(SystemNotification $notification): RedirectResponse
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        if ($notification->user_id === Auth::id()) {
            $notification->update([
                'is_read' => true,
                'read_at' => $notification->read_at ?? now(),
            ]);
        }

        return back()->with('status', 'تم تعليم الإشعار كمقروء.');
    }

    public function markAllAsRead(): RedirectResponse
    {
        if ($redirect = $this->requireUser()) {
            return $redirect;
        }

        SystemNotification::where('user_id', Auth::id())
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return back()->with('status', 'تم تعليم كل الإشعارات كمقروءة.');
    }
}
