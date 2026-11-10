<?php

namespace App\Http\Controllers;

use App\Models\Platform\PlatformApiLog;
use App\Models\Platform\PlatformStatusMapping;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function apiLogs()
    {
        $logs = PlatformApiLog::orderByDesc('id')->paginate(20);

        return view('platform.api-logs', compact('logs'));
    }

    public function statusMappings()
    {
        $mappings = PlatformStatusMapping::orderBy('platform')->paginate(20);

        return view('platform.status-mappings', compact('mappings'));
    }

    public function mappingStore(Request $request)
    {
        $data = $request->validate([
            'platform' => ['required', 'string', 'max:32'],
            'platform_status' => ['required', 'string', 'max:64'],
            'local_status' => ['required', 'string', 'max:64'],
        ]);
        PlatformStatusMapping::create($data);

        return back()->with('success', '状态映射已添加。');
    }
}
