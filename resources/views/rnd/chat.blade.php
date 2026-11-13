@extends('layouts.admin')

@section('title', '项目聊天室')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'chat'])

@php
    $isAdmin = auth()->user()?->hasRole('role_sys_admin') ?? false;
@endphp

<div class="card chat-shell">
    <div class="row g-0 chat-body">
        {{-- 左侧：项目群聊 + 项目成员 --}}
        <div class="col-md-3 col-lg-2 chat-sidebar">
            <div class="chat-project-head">
                <div class="text-truncate fw-semibold" title="{{ $project->project_name }}">
                    <i class="bi bi-folder2-open me-1 text-primary"></i>{{ $project->project_name }}
                </div>
                <div class="text-muted small font-monospace">{{ $project->project_code }}</div>
            </div>

            <a href="{{ route('rnd.projects.chat', $project) }}"
               class="chat-member-item {{ ! $withId ? 'active' : '' }}">
                <span class="chat-avatar bg-primary"><i class="bi bi-people"></i></span>
                <span class="chat-member-meta">
                    <span class="chat-member-name">项目群聊</span>
                    <span class="chat-member-sub">全体成员</span>
                </span>
            </a>

            <div class="chat-sidebar-title">项目成员</div>
            <div class="chat-member-list">
                @forelse ($members as $member)
                    @if ($member['is_me'])
                        @continue
                    @endif
                    <a href="{{ route('rnd.projects.chat', ['project' => $project, 'with' => $member['id']]) }}"
                       class="chat-member-item {{ $withId === $member['id'] ? 'active' : '' }}">
                        <span class="chat-avatar-wrap">
                            @if ($member['avatar_url'])
                                <img src="{{ $member['avatar_url'] }}" alt="{{ $member['name'] }}" class="chat-avatar-img {{ $member['is_online'] ? '' : 'offline' }}">
                            @else
                                <span class="chat-avatar bg-secondary {{ $member['is_online'] ? '' : 'offline' }}">{{ $member['initial'] }}</span>
                            @endif
                            <span class="online-dot {{ $member['is_online'] ? 'online' : 'offline' }}"></span>
                        </span>
                        <span class="chat-member-meta">
                            <span class="chat-member-name">{{ $member['name'] }}</span>
                            <span class="chat-member-sub">{{ $member['is_online'] ? '在线' : '离线' }}{{ $member['department'] ? ' · ' . $member['department'] : '' }}</span>
                        </span>
                    </a>
                @empty
                    <div class="p-3 text-muted small">暂无项目成员</div>
                @endforelse
            </div>
        </div>

        {{-- 右侧：当前会话 --}}
        <div class="col-md-9 col-lg-10 chat-main">
            <div class="chat-main-head">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    @if ($target)
                        @if ($target['avatar_url'])
                            <img src="{{ $target['avatar_url'] }}" class="chat-head-avatar" alt="{{ $target['name'] }}">
                        @else
                            <span class="chat-avatar bg-secondary">{{ $target['initial'] }}</span>
                        @endif
                        <div class="min-w-0">
                            <div class="fw-semibold text-truncate">{{ $target['name'] }}</div>
                            <div class="small text-muted">{{ $target['is_online'] ? '在线' : '离线' }} · 私聊</div>
                        </div>
                    @else
                        <span class="chat-avatar bg-primary"><i class="bi bi-people"></i></span>
                        <div>
                            <div class="fw-semibold">{{ $project->project_name }} · 项目群聊</div>
                            <div class="small text-muted">{{ count($members) }} 位项目成员</div>
                        </div>
                    @endif
                </div>
                <form method="POST" action="{{ route('rnd.projects.chat.clear', $project) }}" class="mb-0"
                      onsubmit="return confirm('确定清空你的留言板吗？此操作只清空你自己的显示记录，不影响其他用户。')">
                    @csrf
                    <input type="hidden" name="with" value="{{ $withId }}">
                    <button class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-trash3 me-1"></i>清空我的留言板
                    </button>
                </form>
            </div>

            <div class="chat-messages" id="chat-messages">
                <div class="text-center text-muted py-5" id="chat-empty">
                    <i class="bi bi-chat-square-dots" style="font-size:42px;"></i>
                    <p class="mt-2 mb-0">{{ $messages->isEmpty() ? '暂无聊天记录，快来开始聊天吧！' : '' }}</p>
                </div>
                <div class="chat-container" id="chat-container"></div>
            </div>

            <div class="chat-composer">
                <form method="POST" action="{{ route('rnd.projects.chat.send', $project) }}" enctype="multipart/form-data" id="chat-form">
                    @csrf
                    <input type="hidden" name="receiver_id" value="{{ $withId }}">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn btn-sm btn-warning text-dark" id="emoji-btn" title="表情">
                                <i class="bi bi-emoji-smile me-1"></i>表情
                            </button>
                            <label class="btn btn-sm btn-info text-white mb-0" title="发送文件">
                                <i class="bi bi-paperclip me-1"></i>文件
                                <input type="file" name="file" id="chat-file-input" class="d-none">
                            </label>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary px-4">
                            <i class="bi bi-send me-1"></i>发送
                        </button>
                    </div>
                    <div class="border rounded bg-white">
                        <div class="px-2 pt-2 border-bottom d-none" id="chat-file-preview">
                            <div class="d-flex align-items-center gap-2 small pb-2">
                                <i class="bi bi-file-earmark text-primary"></i>
                                <span class="flex-grow-1 text-truncate" id="chat-file-name"></span>
                                <button type="button" class="btn btn-sm btn-link text-danger p-0" id="chat-file-clear" title="移除">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </div>
                        </div>
                        <textarea name="content" id="chat-input" rows="2" class="form-control border-0"
                                  placeholder="{{ $target ? '输入私聊消息…' : '输入消息…' }}" style="resize:none;box-shadow:none;"></textarea>
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
        </div>
    </div>
</div>
@endsection

@push('styles')
@include('partials.chat-layout-styles')
@endpush

@push('scripts')
<script>
(function () {
    const initial = @json($payload);
    const feedUrl = @json(route('rnd.projects.chat.feed', ['project' => $project, 'with' => $withId ?: null]));
    const withId = @json($withId);
    const container = document.getElementById('chat-container');
    const empty = document.getElementById('chat-empty');
    const messagesBox = document.getElementById('chat-messages');
    const input = document.getElementById('chat-input');
    const fileInput = document.getElementById('chat-file-input');

    function esc(v) {
        const el = document.createElement('div');
        el.textContent = v == null ? '' : String(v);
        return el.innerHTML;
    }

    function messageHtml(m) {
        const avatar = m.avatar_url
            ? '<img src="' + esc(m.avatar_url) + '" class="message-avatar" alt="' + esc(m.sender) + '">'
            : '<span class="message-avatar bg-secondary">' + esc(m.initial) + '</span>';
        const bubble = m.content ? '<div class="message-bubble">' + esc(m.content) + '</div>' : '';
        const file = m.file_url
            ? '<div class="file-attachment" data-file-url="' + esc(m.file_url) + '" data-preview-url="' + esc(m.file_preview_url || '') + '" data-file-name="' + esc(m.file_name) + '" title="双击预览，右键下载">' +
              '<i class="bi bi-file-earmark-arrow-down"></i><span class="text-truncate">' + esc(m.file_name) + '</span></div>'
            : '';
        const del = m.can_delete
            ? '<form method="POST" action="' + esc(@json(url('/rnd/projects/' . $project->id . '/chat')) + '/' + m.id) + '" class="chat-delete" onsubmit="return confirm(\'确定要删除这条留言吗？\')">' +
              '<input type="hidden" name="_token" value="' + esc(@json(csrf_token())) + '"><input type="hidden" name="_method" value="DELETE">' +
              '<button class="btn btn-sm btn-outline-danger py-0 px-1" title="删除留言"><i class="bi bi-trash"></i></button></form>'
            : '';

        return '<div class="chat-message ' + (m.mine ? 'mine' : '') + '">' + avatar +
            '<div class="message-content"><div class="message-info"><span class="message-username">' + esc(m.sender) +
            '</span><span class="message-time">' + esc(m.time) + '</span></div>' + bubble + file + del + '</div></div>';
    }

    function render(list) {
        container.innerHTML = (list || []).map(messageHtml).join('');
        empty.style.display = (list && list.length) ? 'none' : '';
        if (list && list.length) {
            messagesBox.scrollTop = messagesBox.scrollHeight;
        }
    }

    function bindFiles() {
        container.querySelectorAll('.file-attachment').forEach(function (el) {
            el.addEventListener('dblclick', function () {
                const preview = el.dataset.previewUrl;
                if (preview) {
                    window.open(preview, '_blank');
                } else if (el.dataset.fileUrl) {
                    window.location.href = el.dataset.fileUrl;
                }
            });
            el.addEventListener('contextmenu', function (ev) {
                ev.preventDefault();
                if (el.dataset.fileUrl) {
                    const a = document.createElement('a');
                    a.href = el.dataset.fileUrl;
                    a.download = el.dataset.fileName || '';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }
            });
        });
    }

    let lastSignature = '';
    function signature(list) {
        return (list || []).map(m => m.id).join(',');
    }

    render(initial);
    bindFiles();
    const originalRender = render;
    render = function (list) {
        originalRender(list);
        bindFiles();
    };
    lastSignature = signature(initial);

    async function poll() {
        try {
            const r = await fetch(feedUrl, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
            if (!r.ok) return;
            const data = await r.json();
            const list = data.messages || [];
            if (signature(list) !== lastSignature) {
                lastSignature = signature(list);
                render(list);
            }
        } catch (e) {}
    }
    const timer = setInterval(poll, 4000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) poll();
    });

    document.getElementById('emoji-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        document.getElementById('emoji-panel').classList.toggle('d-none');
    });
    document.getElementById('emoji-panel').addEventListener('click', function (e) {
        const item = e.target.closest('.emoji-item');
        if (!item) return;
        insertEmoji(item.dataset.emoji);
    });
    document.addEventListener('click', function (e) {
        const panel = document.getElementById('emoji-panel');
        if (!panel.classList.contains('d-none') && !e.target.closest('#emoji-panel') && !e.target.closest('#emoji-btn')) {
            panel.classList.add('d-none');
        }
    });
    function insertEmoji(emoji) {
        const start = input.selectionStart || input.value.length;
        const end = input.selectionEnd || input.value.length;
        input.value = input.value.slice(0, start) + emoji + input.value.slice(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
    }

    fileInput.addEventListener('change', function () {
        const file = fileInput.files[0];
        if (!file) return;
        if (file.size > 20 * 1024 * 1024) {
            alert('文件大小不能超过 20MB');
            fileInput.value = '';
            return;
        }
        document.getElementById('chat-file-name').textContent = file.name;
        document.getElementById('chat-file-preview').classList.remove('d-none');
    });
    document.getElementById('chat-file-clear').addEventListener('click', function () {
        fileInput.value = '';
        document.getElementById('chat-file-preview').classList.add('d-none');
    });
})();
</script>
@endpush
