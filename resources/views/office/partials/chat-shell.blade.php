@php
    $emojis = $emojis ?? ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾'];
    $currentUserId = (int) auth()->id();
@endphp

<div class="card chat-shell">
    <div class="row g-0 chat-body chat-body-system">
        {{-- 左侧：仅显示系统所有用户，与研发项目聊天室隔离 --}}
        <div class="col-md-3 col-lg-2 chat-sidebar">
            <div class="chat-project-head">
                <div class="text-truncate fw-semibold">
                    <i class="bi bi-globe2 me-1 text-primary"></i>系统聊天室
                </div>
                <div class="text-muted small">系统所有成员</div>
            </div>

            <div class="chat-sidebar-title">系统成员（{{ $users->count() }}）</div>
            <div class="chat-member-list">
                @forelse ($users as $user)
                    @php
                        $displayName = trim((string) $user->name) ?: $user->username;
                        $deptName = $user->department?->dept_name;
                    @endphp
                    <div class="chat-member-item">
                        <span class="chat-avatar-wrap">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="{{ $displayName }}" class="chat-avatar-img">
                            @else
                                <span class="chat-avatar bg-secondary">{{ mb_substr($displayName, 0, 1) }}</span>
                            @endif
                        </span>
                        <span class="chat-member-meta">
                            <span class="chat-member-name">
                                {{ $displayName }}@if ((int) $user->id === $currentUserId)<span class="text-muted">（我）</span>@endif
                            </span>
                            <span class="chat-member-sub">{{ $deptName ?: '未分配部门' }}</span>
                        </span>
                    </div>
                @empty
                    <div class="p-3 text-muted small">暂无系统成员</div>
                @endforelse
            </div>
        </div>

        {{-- 右侧：系统全员聊天室 --}}
        <div class="col-md-9 col-lg-10 chat-main">
            @if ($room)
                <div class="chat-main-head">
                    <div class="d-flex align-items-center gap-2 min-w-0">
                        <span class="chat-avatar bg-primary"><i class="bi bi-people"></i></span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $room->room_name }}</div>
                            <div class="small text-muted">
                                系统全员 · {{ $users->count() }} 位成员 · {{ $room->messages->count() }} 条消息
                            </div>
                        </div>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages">
                    <div class="chat-container">
                        @forelse ($room->messages as $message)
                            @php
                                $senderName = trim((string) ($message->sender->name ?? '')) ?: ($message->sender->username ?? '系统');
                                $senderInitial = mb_substr($senderName, 0, 1);
                            @endphp
                            <div class="chat-message {{ (int) $message->sender_id === $currentUserId ? 'mine' : '' }}">
                                @if ($message->sender?->avatar_url)
                                    <img src="{{ $message->sender->avatar_url }}" class="message-avatar" alt="{{ $senderName }}">
                                @else
                                    <span class="message-avatar bg-secondary">{{ $senderInitial }}</span>
                                @endif
                                <div class="message-content">
                                    <div class="message-info">
                                        <span class="message-username">{{ $senderName }}</span>
                                        <span class="message-time">{{ $message->created_at?->format('H:i') }}</span>
                                    </div>
                                    @if ($message->content)
                                        <div class="message-bubble">{{ $message->content }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5" id="chat-empty">
                                <i class="bi bi-chat-square-dots" style="font-size:42px;"></i>
                                <p class="mt-2 mb-0">暂无聊天记录，快来开始聊天吧！</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="chat-composer">
                    <form method="POST" action="{{ route('office.chat.send', $room) }}" id="chat-form">
                        @csrf
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-warning text-dark" id="emoji-btn" title="表情">
                                    <i class="bi bi-emoji-smile me-1"></i>表情
                                </button>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary px-4">
                                <i class="bi bi-send me-1"></i>发送
                            </button>
                        </div>
                        <div class="border rounded bg-white">
                            <textarea name="content" id="chat-input" rows="2" class="form-control border-0"
                                      placeholder="输入消息…" style="resize:none;box-shadow:none;" required></textarea>
                        </div>
                        @error('content')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                    <div class="emoji-panel d-none" id="emoji-panel">
                        <div class="emoji-grid">
                            @foreach ($emojis as $emoji)
                                <span class="emoji-item" data-emoji="{{ $emoji }}">{{ $emoji }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            @else
                <div class="chat-main-head">
                    <div class="fw-semibold">系统聊天室</div>
                </div>
                <div class="chat-messages d-flex align-items-center justify-content-center">
                    <div class="text-center text-muted">
                        <i class="bi bi-chat-square-dots" style="font-size:42px;"></i>
                        <p class="mt-2 mb-0">暂无聊天室</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
@include('partials.chat-layout-styles')
@endpush

@push('scripts')
<script>
(function () {
    const messagesBox = document.getElementById('chat-messages');
    if (messagesBox) {
        messagesBox.scrollTop = messagesBox.scrollHeight;
    }

    const emojiBtn = document.getElementById('emoji-btn');
    const emojiPanel = document.getElementById('emoji-panel');
    const input = document.getElementById('chat-input');
    if (!emojiBtn || !emojiPanel || !input) return;

    emojiBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        emojiPanel.classList.toggle('d-none');
    });
    emojiPanel.addEventListener('click', function (e) {
        const item = e.target.closest('.emoji-item');
        if (!item) return;
        const emoji = item.dataset.emoji;
        const start = input.selectionStart || input.value.length;
        const end = input.selectionEnd || input.value.length;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
    });
    document.addEventListener('click', function (e) {
        if (!emojiPanel.classList.contains('d-none')
            && !e.target.closest('#emoji-panel')
            && !e.target.closest('#emoji-btn')) {
            emojiPanel.classList.add('d-none');
        }
    });
})();
</script>
@endpush
