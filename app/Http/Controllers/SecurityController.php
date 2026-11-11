<?php

namespace App\Http\Controllers;

use App\Models\Security\LoginLog;
use App\Models\Security\OperationLog;
use App\Models\Security\SecurityEvent;
use App\Support\TenancyContext;
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

        if ($tab === 'operations') {
            $q = trim((string) $request->query('q', ''));
            $action = trim((string) $request->query('action', ''));

            $query = OperationLog::query();

            if ($tenantId = app(TenancyContext::class)->id()) {
                $query->where('tenant_id', $tenantId);
            }

            if ($q !== '') {
                $keyword = mb_substr($q, 0, 64);
                $query->where(function ($w) use ($keyword) {
                    $w->where('actor_name', 'like', '%' . $keyword . '%')
                        ->orWhere('target_type', 'like', '%' . $keyword . '%')
                        ->orWhere('target_label', 'like', '%' . $keyword . '%')
                        ->orWhere('target_id', $keyword);
                });
            }

            if (in_array($action, ['created', 'updated', 'deleted'], true)) {
                $query->where('action', $action);
            }

            $items = $query->orderByDesc('id')->paginate(30, ['*'], 'o')->withQueryString();

            return view('security.operations', compact('items', 'tab', 'q', 'action'));
        }

        $items = LoginLog::orderByDesc('id')->paginate(20, ['*'], 'l');
        return view('security.logs', compact('items', 'tab'));
    }
}
