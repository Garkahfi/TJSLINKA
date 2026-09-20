<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SuperAdminNotificationController extends Controller
{
    public function index(Request $r): View
    {
        $items = $r->user()->adminNotifications()->latest('created_at')->get();
        $r->user()->adminNotifications()->where('is_read', false)->update(['is_read' => true]);

        return view('superadmin.notifications', compact('items'));
    }

    public function unread(Request $r): JsonResponse
    {
        return response()->json(['count' => $r->user()->adminNotifications()->where('is_read', false)->count()]);
    }
}
