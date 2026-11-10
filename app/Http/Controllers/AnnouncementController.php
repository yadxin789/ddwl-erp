<?php

namespace App\Http\Controllers;

use App\Models\Office\Announcement;
use Illuminate\Http\Request;

/**
 * 公告通知（G8）：全员可读；行政/管理员发布。
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::query()
            ->where('status', 1)
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate(15);

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'in:general,policy,notice,urgent'],
        ]);

        Announcement::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'category' => $data['category'] ?? 'general',
            'publisher_id' => auth()->id(),
            'published_at' => now(),
            'status' => 1,
        ]);

        return back()->with('success', '公告已发布。');
    }

    public function toggle(Announcement $announcement)
    {
        $announcement->update(['status' => $announcement->status ? 0 : 1]);

        return back()->with('success', '公告已' . ($announcement->status ? '重新上架' : '下架'));
    }
}
