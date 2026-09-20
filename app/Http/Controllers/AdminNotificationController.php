<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminNotificationController extends Controller
{
    public function __invoke(Request $r): View
    {
        $items = $r->user()->adminNotifications()->latest('created_at')->get();
        $r->user()->adminNotifications()->where('is_read', false)->update(['is_read' => true]);

        return view('admin.notifications', compact('items'));
    }
}
