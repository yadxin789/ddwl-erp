<?php

namespace App\Http\Controllers;

use App\Models\Office\ChatRoom;
use App\Models\Office\SystemNotification;
use App\Models\Office\TodoItem;
use App\Models\Organization\User;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class OfficeController extends Controller
{
    public function __construct(protected FileService $files)
    {
    }

    /* ---------- 个人资料 ---------- */
    public function profile()
    {
        return view('office.profile', ['user' => Auth::user()]);
    }

    public function password()
    {
        return view('office.password');
    }

    /* ---------- 知识库 ---------- */
    public function knowledge(Request $request)
    {
        $q = \App\Models\System\MediaFile::query()
            ->where('category', 'collab_doc')
            ->when($request->q, fn($qb, $kw) => $qb->where('file_name', 'like', "%{$kw}%"))
            ->orderByDesc('id')->paginate(15);

        return view('office.knowledge', ['files' => $q, 'q' => $request->q]);
    }

    public function knowledgeUpload(Request $request)
    {
        $request->validate(['file' => ['required', 'file', 'max:20480']]);
        $this->files->upload($request->file('file'), 'collab_doc');

        return back()->with('success', '文档已上传到知识库。');
    }

    public function profileUpdate(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:128'],
            'bio' => ['nullable', 'string', 'max:255'],
        ]);
        Auth::user()->update($data);

        return back()->with('success', '个人资料已更新。');
    }

    /* ---------- 通知 ---------- */
    public function notifications()
    {
        $items = SystemNotification::where('user_id', Auth::id())
            ->orderByDesc('id')->paginate(15);
        SystemNotification::where('user_id', Auth::id())->where('is_read', false)->update(['is_read' => true]);

        return view('office.notifications', compact('items'));
    }

    /* ---------- 待办 ---------- */
    public function todos(Request $request)
    {
        $query = TodoItem::where('user_id', Auth::id());
        if ($request->boolean('pending')) {
            $query->where('is_finished', false);
        }
        $items = $query->orderByDesc('id')->paginate(15);

        return view('office.todos', compact('items'));
    }

    public function todoFinish(TodoItem $todo)
    {
        if ($todo->user_id !== Auth::id()) {
            abort(403);
        }
        $todo->update(['is_finished' => true, 'finished_at' => now()]);

        return back()->with('success', '待办已完成。');
    }

    /* ---------- 聊天室 ---------- */
    public function chatRooms()
    {
        $rooms = ChatRoom::orderByDesc('id')->paginate(15);

        return view('office.chat-rooms', compact('rooms'));
    }

    public function chatShow(ChatRoom $room)
    {
        $room->load('messages.sender');

        return view('office.chat-show', compact('room'));
    }

    public function chatSend(Request $request, ChatRoom $room)
    {
        $request->validate(['content' => ['required', 'string', 'max:2000']]);
        \App\Models\Office\ChatMessage::create([
            'room_id' => $room->id,
            'sender_id' => Auth::id(),
            'msg_type' => 'text',
            'content' => $request->input('content'),
            'created_at' => now(),
        ]);

        return back();
    }
}
