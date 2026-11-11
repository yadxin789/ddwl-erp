<?php

namespace App\Http\Controllers;

use App\Models\Office\ChatRoom;
use App\Models\Office\SystemNotification;
use App\Models\Office\TodoItem;
use App\Models\Organization\User;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfficeController extends Controller
{
    /** 默认头像仓库（只允许这 5 个固定文件，避免路径穿越）。 */
    private const DEFAULT_AVATARS = [
        'defaults/avatar1.svg',
        'defaults/avatar2.svg',
        'defaults/avatar3.svg',
        'defaults/avatar4.svg',
        'defaults/avatar5.svg',
    ];

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

    public function avatar()
    {
        return view('office.avatar', ['user' => Auth::user()]);
    }

    /**
     * 头像更新：上传图片或选择默认头像。
     * 自定义头像落 public 磁盘 storage/app/public/avatars，默认头像只允许固定清单。
     */
    public function avatarUpdate(Request $request)
    {
        $user = Auth::user();

        if ($request->filled('default_avatar')) {
            $request->validate([
                'default_avatar' => ['required', 'string', Rule::in(self::DEFAULT_AVATARS)],
            ]);

            if ($user->avatar && ! str_starts_with($user->avatar, 'defaults/')) {
                Storage::disk('public')->delete('avatars/' . $user->avatar);
            }

            $user->update(['avatar' => $request->input('default_avatar')]);

            return back()->with('success', '已切换为默认头像。');
        }

        $request->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,jpg,png,gif', 'max:2048'],
        ]);

        $file = $request->file('avatar');
        $fileName = now()->format('YmdHis') . '_' . Str::random(8) . '.' . strtolower($file->extension());
        $file->storeAs('avatars', $fileName, 'public');

        if ($user->avatar && ! str_starts_with($user->avatar, 'defaults/')) {
            Storage::disk('public')->delete('avatars/' . $user->avatar);
        }

        $user->update(['avatar' => $fileName]);

        return back()->with('success', '头像上传成功。');
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
            'email' => ['nullable', 'email', 'max:128', Rule::unique('users', 'email')->ignore(Auth::id())],
            'bio' => ['nullable', 'string', 'max:500'],
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
