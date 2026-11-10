<?php

namespace App\Http\Controllers;

use App\Models\Security\LoginLog;
use App\Models\Security\SecurityEvent;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function logs(Request $request)
    {
        $tab = $request->query('tab', 'login');

        if ($tab === 'events') {
            $items = SecurityEvent::with('user')->orderByDesc('id')->paginate(20, ['*'], 'e');
            return view('security.events', compact('items', 'tab'));
        }

        $items = LoginLog::orderByDesc('id')->paginate(20, ['*'], 'l');
        return view('security.logs', compact('items', 'tab'));
    }
}
