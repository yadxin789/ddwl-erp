<?php

namespace App\Http\Controllers;

use App\Models\Office\ChatClearState;
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
    public function chatRooms(Request $request)
    {
        $withId = (int) $request->query('with', 0);
        $target = null;

        if ($withId && $withId !== Auth::id()) {
            $target = User::with('department')->where('status', 1)->find($withId);
            if (! $target) {
                return redirect()->route('office.chat.rooms')
                    ->withErrors(['with' => '该成员不存在或已停用。']);
            }
            $room = $this->systemPrivateRoom($withId);
        } else {
            $room = $this->systemChatRoom();
        }

        $this->loadSystemMessages($room);
        $users = User::with('department')
            ->where('status', 1)
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        return view('office.chat-rooms', compact('room', 'users', 'target', 'withId'));
    }

    public function chatShow(ChatRoom $room)
    {
        $target = null;

        if ($room->room_type === 'system_private') {
            $ids = $this->systemPrivateUserIds($room);
            if (! in_array((int) Auth::id(), $ids, true)) {
                return redirect()->route('office.chat.rooms');
            }
            $otherId = (int) collect($ids)->first(fn ($id) => $id !== (int) Auth::id());
            $target = User::with('department')->where('status', 1)->find($otherId);
            if (! $target) {
                return redirect()->route('office.chat.rooms');
            }
        } elseif ($room->room_type !== 'system_all') {
            // 全局聊天室与研发项目聊天室隔离：项目群/研发私聊不允许在全局入口打开。
            return redirect()->route('office.chat.rooms');
        }

        $this->loadSystemMessages($room);
        $users = User::with('department')
            ->where('status', 1)
            ->orderBy('department_id')
            ->orderBy('name')
            ->get();

        $withId = $target?->id ?? 0;

        return view('office.chat-show', compact('room', 'users', 'target', 'withId'));
    }

    /** 按当前用户的清空游标加载系统聊天室消息，只影响自己的显示范围。 */
    private function loadSystemMessages(ChatRoom $room): void
    {
        $clear = ChatClearState::where('user_id', Auth::id())
            ->where('room_id', $room->id)
            ->value('cleared_at');

        $messages = \App\Models\Office\ChatMessage::with('sender')
            ->where('room_id', $room->id)
            ->when($clear, fn ($q) => $q->where('created_at', '>', $clear))
            ->orderBy('id')
            ->get();

        $room->setRelation('messages', $messages);
    }

    /** 系统全员聊天室：独立于研发项目聊天室，所有登录成员共用。 */
    private function systemChatRoom(): ChatRoom
    {
        return ChatRoom::firstOrCreate(
            ['room_type' => 'system_all'],
            ['room_name' => '系统全员聊天室', 'creator_id' => Auth::id()]
        );
    }

    /** 系统成员私聊：独立于项目私聊，按两名成员编号生成固定房间。 */
    private function systemPrivateRoom(int $otherId): ChatRoom
    {
        $ids = [(int) Auth::id(), $otherId];
        sort($ids);

        return ChatRoom::firstOrCreate(
            ['room_type' => 'system_private', 'room_name' => '系统私聊:' . $ids[0] . ':' . $ids[1]],
            ['creator_id' => Auth::id()]
        );
    }

    /** @return int[] */
    private function systemPrivateUserIds(ChatRoom $room): array
    {
        if ($room->room_type !== 'system_private') {
            return [];
        }
        if (! preg_match('/^系统私聊:(\d+):(\d+)$/u', (string) $room->room_name, $m)) {
            return [];
        }

        return [(int) $m[1], (int) $m[2]];
    }

    public function chatSend(Request $request, ChatRoom $room)
    {
        $isSystemAll = $room->room_type === 'system_all';
        $isSystemPrivate = $room->room_type === 'system_private'
            && in_array((int) Auth::id(), $this->systemPrivateUserIds($room), true);

        if (! $isSystemAll && ! $isSystemPrivate) {
            return redirect()->route('office.chat.rooms');
        }

        $request->validate([
            'content' => ['nullable', 'string', 'max:2000'],
            'file' => ['nullable', 'file', 'max:20480'],
        ]);

        $content = trim((string) $request->input('content', ''));
        if ($content === '' && ! $request->hasFile('file')) {
            return back()->withErrors(['content' => '消息内容不能为空。']);
        }

        $fileId = null;
        $type = 'text';
        if ($request->hasFile('file')) {
            $fileId = $this->files->upload($request->file('file'), 'chat_file', 'chat_room', $room->id)->id;
            $type = 'file';
        }

        \App\Models\Office\ChatMessage::create([
            'room_id' => $room->id,
            'sender_id' => Auth::id(),
            'msg_type' => $type,
            'content' => $content,
            'file_id' => $fileId,
            'created_at' => now(),
        ]);

        return back();
    }

    /** 清空我的留言板：只写当前用户的显示游标，不删除真实消息。 */
    public function chatClear(Request $request, ChatRoom $room)
    {
        $ids = $this->systemPrivateUserIds($room);
        $isSystemAll = $room->room_type === 'system_all';
        $isSystemPrivate = $room->room_type === 'system_private'
            && in_array((int) Auth::id(), $ids, true);

        if (! $isSystemAll && ! $isSystemPrivate) {
            return redirect()->route('office.chat.rooms');
        }

        ChatClearState::updateOrCreate(
            ['user_id' => Auth::id(), 'room_id' => $room->id],
            ['cleared_at' => now()]
        );

        if ($isSystemPrivate) {
            $otherId = (int) collect($ids)->first(fn ($id) => $id !== (int) Auth::id());

            return redirect()->route('office.chat.rooms', ['with' => $otherId]);
        }

        return redirect()->route('office.chat.rooms');
    }
}
