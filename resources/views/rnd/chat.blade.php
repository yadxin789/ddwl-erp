@extends('layouts.admin')

@section('title', '项目聊天室')

@section('content')
@include('rnd.partials.project-tabs', ['activeTab' => 'chat'])

@if (session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif
@if ($errors->any())
<div class="alert alert-danger py-2">{{ $errors->first() }}</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-chat-dots me-1"></i>项目群聊 · {{ $project->project_name }}</span>
        <span class="text-muted small">仅项目参与人可见 · 消息保存在本机数据库，断网可用</span>
    </div>

    <div id="chatBox" class="card-body" style="height:520px;overflow-y:auto;background:#f8f9fa;">
        @forelse ($messages as $m)
        @php $mine = (int) $m->sender_id === (int) auth()->id(); $nm = $m->sender->identity_label ?? '—'; @endphp
        <div class="d-flex mb-2 {{ $mine ? 'justify-content-end' : '' }}" data-mid="{{ $m->id }}">
            @if (! $mine)
            <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width:32px;height:32px;font-size:12px;">{{ mb_substr($nm, 0, 1) }}</div>
            @endif
            <div class="px-3 py-2 rounded" style="max-width:70%;background:{{ $mine ? '#0d6efd' : '#ffffff' }};color:{{ $mine ? '#fff' : '#212529' }};border:1px solid #dee2e6;">
                <div class="small {{ $mine ? 'text-white-50' : 'text-muted' }}">{{ $nm }} · {{ $m->created_at?->format('m-d H:i') }}</div>
                @if ($m->content)<div style="white-space:pre-wrap;">{{ $m->content }}</div>@endif
                @if ($m->file_id)
                <div class="mt-1">
                    <a class="link-light" href="{{ route('files.download', $m->file_id) }}" style="color:{{ $mine ? '#fff' : '#0d6efd' }};">
                        <i class="bi bi-paperclip"></i>{{ \App\Models\System\MediaFile::find($m->file_id)?->original_name ?? '附件' }}
                    </a>
                </div>
                @endif
            </div>
        </div>
        @empty
        <div class="text-center text-muted py-5" id="emptyHint">还没有消息，发第一条吧。</div>
        @endforelse
    </div>

    <div class="card-footer">
        <form method="POST" action="{{ route('rnd.projects.chat.send', $project) }}" enctype="multipart/form-data" class="row g-2 align-items-end">
            @csrf
            <div class="col">
                <textarea name="content" class="form-control" rows="2" maxlength="2000" placeholder="输入消息，Enter 发送（Shift+Enter 换行）" id="msgInput"></textarea>
            </div>
            <div class="col-auto" style="width:200px">
                <input type="file" name="file" class="form-control form-control-sm">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary">发送</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    var box = document.getElementById('chatBox');
    var feedUrl = @json(route('rnd.projects.chat.feed', $project));
    var lastId = {{ (int) ($messages->last()->id ?? 0) }};
    var me = @json(auth()->user()->identity_label ?? auth()->user()->name);

    box.scrollTop = box.scrollHeight;

    function esc(s) { return (s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function append(m) {
        var hint = document.getElementById('emptyHint');
        if (hint) { hint.remove(); }
        var row = document.createElement('div');
        row.className = 'd-flex mb-2' + (m.mine ? ' justify-content-end' : '');
        row.dataset.mid = m.id;
        var avatar = m.mine ? '' :
            '<div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center me-2 flex-shrink-0" style="width:32px;height:32px;font-size:12px;">' + esc(m.initial) + '</div>';
        var body = '';
        if (m.content) { body += '<div style="white-space:pre-wrap;">' + esc(m.content) + '</div>'; }
        if (m.file_id) {
            body += '<div class="mt-1"><a style="color:' + (m.mine ? '#fff' : '#0d6efd') + '" href="/files/' + m.file_id + '/download"><i class="bi bi-paperclip"></i>' + esc(m.file_name || '附件') + '</a></div>';
        }
        row.innerHTML = avatar +
            '<div class="px-3 py-2 rounded" style="max-width:70%;background:' + (m.mine ? '#0d6efd' : '#ffffff') +
            ';color:' + (m.mine ? '#fff' : '#212529') + ';border:1px solid #dee2e6;">' +
            '<div class="small ' + (m.mine ? 'text-white-50' : 'text-muted') + '">' + esc(m.sender) + ' · ' + esc(m.time) + '</div>' + body + '</div>';
        box.appendChild(row);
        box.scrollTop = box.scrollHeight;
    }

    function poll() {
        fetch(feedUrl + '?after=' + lastId, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.messages) { return; }
                d.messages.forEach(function (m) { append(m); });
                if (d.last) { lastId = d.last; }
            })
            .catch(function () { /* 服务未响应时静默，下一轮重试 */ });
    }
    setInterval(poll, 3000);

    var input = document.getElementById('msgInput');
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            input.form.submit();
        }
    });
})();
</script>
@endsection
