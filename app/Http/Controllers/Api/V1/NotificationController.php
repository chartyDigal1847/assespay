<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Support\PortalSession;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $portalId = PortalSession::portalId($request);

        $items = Notification::where('portal_user_id', $portalId)
            ->orWhereNull('portal_user_id')
            ->latest()
            ->paginate(20);

        return response()->json(['data' => $items]);
    }

    public function markRead(Notification $notification)
    {
        $notification->update(['read_at' => now()]);

        return response()->json(['data' => $notification]);
    }
}
