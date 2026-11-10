<?php

namespace App\Http\Controllers;

use App\Models\Office\Announcement;
use App\Models\Office\TodoItem;
use Illuminate\Http\Request;

/**
 * 支援中心（G12）：跨岗位支援请求的聚合入口（只读汇总 + 支援待办）。
 */
class SupportController extends Controller
{
    public function index(Request $request)
    {
        $todos = TodoItem::query()
            ->when($request->user_id, fn ($q, $uid) => $q->where('user_id', $uid))
            ->where('is_finished', false)
            ->where(function ($q) {
                $q->where('todo_type', 'like', '%support%')
                    ->orWhere('todo_type', 'like', '%assist%');
            })
            ->orderBy('deadline')
            ->limit(20)
            ->get();

        $announcements = Announcement::query()
            ->where('status', 1)
            ->orderByDesc('published_at')
            ->limit(5)
            ->get();

        return view('support.center', compact('todos', 'announcements'));
    }
}
