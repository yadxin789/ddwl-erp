@php
    $emojis = $emojis ?? ['😀','😃','😄','😁','😆','😅','😂','🤣','😊','😇','🙂','🙃','😉','😌','😍','🥰','😘','😗','😙','😚','😋','😛','😝','😜','🤪','🤨','🧐','🤓','😎','🥳','😏','😒','😞','😔','😟','😕','🙁','☹️','😣','😖','😫','😩','🥺','😢','😭','😤','😠','😡','🤬','🤗','🤔','🤭','🤫','🤥','😶','😐','😑','😬','🙄','😯','😦','😧','😮','😲','🥱','😴','🤤','😪','😵','🤐','🥴','🤢','🤮','🤧','😷','🤒','🤕','🤑','🤠','😈','👿','👹','👺','🤡','💩','👻','💀','☠️','👽','👾','🤖','🎃','😺','😸','😹','😻','😼','😽','🙀','😿','😾'];
    $currentUserId = (int) auth()->id();
    $targetId = (int) (($target ?? null)->id ?? 0);
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

            <div class="chat-sidebar-title">系统成员（{{ $users->count() }}） <span class="fw-normal text-muted">· 双击可私聊</span></div>
            <div class="chat-member-list">
                @forelse ($users as $user)
                    @php
                        $displayName = trim((string) $user->name) ?: $user->username;
                        $deptName = $user->department?->dept_name;
                        $isSelf = (int) $user->id === $currentUserId;
                        $privateUrl = $isSelf
                            ? route('office.chat.rooms')
                            : route('office.chat.rooms', ['with' => $user->id]);
                    @endphp
                    <a href="{{ $privateUrl }}"
                       class="chat-member-item {{ ! $isSelf && $targetId === (int) $user->id ? 'active' : '' }}"
                       title="{{ $isSelf ? '当前登录用户' : '双击进入与 ' . $displayName . ' 的私聊' }}">
                        <span class="chat-avatar-wrap">
                            @if ($user->avatar_url)
                                <img src="{{ $user->avatar_url }}" alt="{{ $displayName }}" class="chat-avatar-img">
                            @else
                                <span class="chat-avatar bg-secondary">{{ mb_substr($displayName, 0, 1) }}</span>
                            @endif
                        </span>
                        <span class="chat-member-meta">
                            <span class="chat-member-name">
                                {{ $displayName }}@if ($isSelf)<span class="text-muted">（我）</span>@endif
                            </span>
                            <span class="chat-member-sub">{{ $deptName ?: '未分配部门' }}</span>
                        </span>
                    </a>
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
                        @if ($target)
                            @if ($target->avatar_url)
                                <img src="{{ $target->avatar_url }}" class="chat-head-avatar" alt="{{ $target->name }}">
                            @else
                                <span class="chat-avatar bg-secondary">{{ mb_substr(trim((string) $target->name), 0, 1) }}</span>
                            @endif
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $target->name }}</div>
                                <div class="small text-muted">
                                    {{ $target->department?->dept_name ?: '未分配部门' }} · 私聊 · {{ $room->messages->count() }} 条消息
                                </div>
                            </div>
                        @else
                            <span class="chat-avatar bg-primary"><i class="bi bi-people"></i></span>
                            <div class="min-w-0">
                                <div class="fw-semibold text-truncate">{{ $room->room_name }}</div>
                                <div class="small text-muted">
                                    系统全员 · {{ $users->count() }} 位成员 · {{ $room->messages->count() }} 条消息
                                </div>
                            </div>
                        @endif
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <form method="POST" action="{{ route('office.chat.clear', $room) }}" class="mb-0"
                              onsubmit="return confirm('确定清空你自己的留言板吗？此操作只清空你的显示记录，不会删除任何聊天记录。')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                <i class="bi bi-trash3 me-1"></i>清空我的留言板
                            </button>
                        </form>
                        @if ($target)
                            <a href="{{ route('office.chat.rooms') }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people me-1"></i>返回全员聊天
                            </a>
                        @endif
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
                                    @if ($message->file)
                                        @php
                                            $fileName = $message->file->file_name;
                                            $isImage = str_starts_with((string) $message->file->mime_type, 'image/');
                                        @endphp
                                        @if ($isImage)
                                            <a href="{{ route('files.preview', $message->file) }}" target="_blank" rel="noopener" class="d-inline-block mt-1">
                                                <img src="{{ route('files.preview', $message->file) }}" alt="{{ $fileName }}" class="chat-image-attachment">
                                            </a>
                                        @else
                                            <a href="{{ route('files.download', $message->file) }}" class="file-attachment" title="下载 {{ $fileName }}">
                                                <i class="bi bi-file-earmark-arrow-down"></i>
                                                <span class="text-truncate">{{ $fileName }}</span>
                                                <span class="text-muted small flex-shrink-0">{{ number_format(max(1, (int) $message->file->file_size) / 1024, 1) }} KB</span>
                                            </a>
                                        @endif
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
                    <form method="POST" action="{{ route('office.chat.send', $room) }}" enctype="multipart/form-data" id="chat-form">
                        @csrf
                        <input type="file" name="file" id="chat-file-input" class="d-none">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn btn-sm btn-warning text-dark" id="emoji-btn" title="表情">
                                    <i class="bi bi-emoji-smile me-1"></i>表情
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="chat-image-btn" title="上传图片">
                                    <i class="bi bi-image me-1"></i>图片
                                </button>
                                <button type="button" class="btn btn-sm btn-info text-white" id="chat-file-btn" title="上传文件">
                                    <i class="bi bi-paperclip me-1"></i>文件
                                </button>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary px-4">
                                <i class="bi bi-send me-1"></i>发送
                            </button>
                        </div>
                        <div class="border rounded bg-white">
                            <div class="chat-file-preview d-none" id="chat-file-preview">
                                <img id="chat-file-thumb" class="d-none" alt="图片预览">
                                <i id="chat-file-icon" class="bi bi-file-earmark text-primary" style="font-size:22px;"></i>
                                <span class="flex-grow-1 text-truncate small" id="chat-file-name"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" id="chat-file-clear" title="移除附件">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                            <textarea name="content" id="chat-input" rows="2" class="form-control border-0"
                                      placeholder="{{ $target ? '输入私聊消息…' : '输入消息…' }}" style="resize:none;box-shadow:none;"></textarea>
                        </div>
                        @error('content')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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

    const fileInput = document.getElementById('chat-file-input');
    const imageBtn = document.getElementById('chat-image-btn');
    const fileBtn = document.getElementById('chat-file-btn');
    const filePreview = document.getElementById('chat-file-preview');
    const fileThumb = document.getElementById('chat-file-thumb');
    const fileIcon = document.getElementById('chat-file-icon');
    const fileName = document.getElementById('chat-file-name');
    const fileClear = document.getElementById('chat-file-clear');
    const form = document.getElementById('chat-form');

    imageBtn.addEventListener('click', function () {
        fileInput.value = '';
        fileInput.accept = 'image/png,image/jpeg,image/gif,image/webp,image/bmp';
        fileInput.click();
    });
    fileBtn.addEventListener('click', function () {
        fileInput.value = '';
        fileInput.accept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar,.7z';
        fileInput.click();
    });

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) {
            alert('文件大小不能超过 20MB');
            fileInput.value = '';
            return;
        }

        fileName.textContent = file.name;
        if (file.type.startsWith('image/')) {
            fileThumb.src = URL.createObjectURL(file);
            fileThumb.classList.remove('d-none');
            fileIcon.classList.add('d-none');
        } else {
            fileThumb.src = '';
            fileThumb.classList.add('d-none');
            fileIcon.classList.remove('d-none');
        }
        filePreview.classList.remove('d-none');
    });

    fileClear.addEventListener('click', function () {
        fileInput.value = '';
        fileThumb.src = '';
        filePreview.classList.add('d-none');
    });

    form.addEventListener('submit', function (e) {
        if (!input.value.trim() && !fileInput.files.length) {
            e.preventDefault();
            alert('请输入消息内容或选择图片/文件。');
            input.focus();
        }
    });
})();
</script>
@endpush
