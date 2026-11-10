<?php

namespace App\Http\Controllers;

use App\Models\Sync\SyncTask;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function index()
    {
        $tasks = SyncTask::with('logs')->orderByDesc('id')->paginate(15);

        return view('sync.index', compact('tasks'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'target' => ['required', 'string', 'max:64'],
            'direction' => ['required', 'in:pull,push'],
            'data_type' => ['required', 'string', 'max:64'],
            'cron_expr' => ['nullable', 'string', 'max:64'],
        ]);
        $data['status'] = 'active';
        SyncTask::create($data);

        return back()->with('success', '同步任务已创建。');
    }

    public function toggle(SyncTask $task)
    {
        $task->update(['status' => $task->status === 'active' ? 'paused' : 'active']);

        return back()->with('success', '任务状态已切换。');
    }
}
