<?php

namespace App\Http\Controllers;

use App\Support\Notifications;

class NotificationController extends Controller
{
    /** «Marcar como leídas» del panel de notificaciones. */
    public function markRead(Notifications $notifications)
    {
        $notifications->markRead(auth()->user());

        return back();
    }
}
