<?php

namespace App\Http\Controllers\Creator;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class CreatorNotificationController extends Controller
{
    public function markRead(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        $this->ensureOwnsNotification($request, $notification);

        $notification->markAsRead();

        return back()->with('success', 'Alert marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Alerts marked as read.');
    }

    private function ensureOwnsNotification(Request $request, DatabaseNotification $notification): void
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->id,
            404,
        );
    }
}
