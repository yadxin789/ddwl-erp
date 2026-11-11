@extends('layouts.admin')

@section('title', 'AI 助手')

@section('content')
<style>
    .ai-chat-page { display:flex; gap:14px; height:calc(100vh - 150px); min-height:560px; }
    .ai-side { width:270px; flex:0 0 270px; background:#fff; border:1px solid #e5e7eb; border-radius:10px; display:flex; flex-direction:column; overflow:hidden; }
    .ai-side-head { padding:10px; border-bottom:1px solid #eef0f2; }
    .ai-side-list { flex:1; overflow-y:auto; padding:6px; }
    .conv-item { padding:8px; border:1px solid transparent; border-radius:8px; margin-bottom:4px; }
    .conv-item:hover { background:#f8fafc; }
    .conv-item.active { background:#eff6ff; border-color:#bfdbfe; }
    .conv-title { font-size:13px; font-weight:600; color:#1f2937; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .conv-meta { font-size:11px; color:#94a3b8; display:flex; justify-content:space-between; gap:6px; }
    .conv-more { color:#94a3b8; cursor:pointer; padding:0 4px; }
    .conv-more:hover { color:#2563eb; }
    .ai-main { flex:1; min-width:0; display:flex; flex-direction:column; background:#fff; border:1px solid #e5e7eb; border-radius:10px; overflow:hidden; position:relative; }
    .ai-main-head { padding:10px 14px; border-bottom:1px solid #eef0f2; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
    .ai-main-body { flex:1; overflow-y:auto; padding:16px; background:#f8fafc; }
    .ai-msg { display:flex; gap:8px; margin-bottom:14px; }
    .ai-msg.user { justify-content:flex-end; }
    .ai-avatar { width:30px; height:30px; flex:0 0 30px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; font-size:13px; }
    .ai-avatar.assistant { background:linear-gradient(135deg,#2563eb,#1d4ed8); }
    .ai-avatar.user { background:#0ea5e9; }
    .ai-bubble { max-width:78%; padding:10px 12px; border-radius:10px; font-size:14px; line-height:1.7; word-break:break-word; white-space:pre-wrap; }
    .ai-bubble.user { background:#2563eb; color:#fff; }
    .ai-bubble.assistant { background:#fff; border:1px solid #e5e7eb; }
    .ai-attach { display:flex; flex-wrap:wrap; gap:8px; margin-bottom:6px; }
    .ai-attach-item { display:flex; align-items:center; gap:6px; max-width:240px; padding:4px 8px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; font-size:12px; color:#374151; text-decoration:none; }
    .ai-attach-item img { width:44px; height:44px; object-fit:cover; border-radius:4px; }
    .ai-composer { border-top:1px solid #eef0f2; padding:10px 12px; background:#fff; }
    .ai-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
    .ai-chip { display:inline-flex; align-items:center; gap:6px; max-width:100%; background:#f3f4f6; border:1px solid #e5e7eb; border-radius:14px; padding:3px 10px; font-size:12px; color:#374151; }
    .ai-chip .remove { cursor:pointer; color:#94a3b8; }
    .ai-chip .remove:hover { color:#dc3545; }
    .ai-tips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
    .ai-tip { border:1px dashed #cbd5e1; background:#fff; color:#475569; font-size:12px; border-radius:12px; padding:2px 9px; }
    .ai-tip:hover { border-color:#2563eb; color:#2563eb; }
    .ai-tools { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
    .ai-empty { text-align:center; color:#94a3b8; padding:50px 10px; }
    .ai-suggest { display:inline-block; margin:5px 4px 0; padding:5px 10px; border:1px solid #e5e7eb; border-radius:16px; background:#fff; cursor:pointer; color:#475569; font-size:12px; }
    .ai-suggest:hover { border-color:#2563eb; color:#2563eb; }
    .ai-drop-hint { position:absolute; inset:0; z-index:5; display:none; align-items:center; justify-content:center; border:2px dashed #2563eb; border-radius:10px; background:rgba(37,99,235,.06); color:#2563eb; font-weight:600; pointer-events:none; }
    .ai-chat-page.dragging .ai-drop-hint { display:flex; }
    .msg-time { font-size:11px; color:#94a3b8; margin-top:3px; }
    .copy-btn { cursor:pointer; margin-left:8px; }
    .copy-btn:hover { color:#2563eb; }
    @media (max-width: 768px) {
        .ai-chat-page { flex-direction:column; height:auto; }
        .ai-side { width:100%; flex:none; max-height:230px; }
        .ai-main { min-height:70vh; }
    }
</style>

<div class="container-fluid px-0">
    @if (session('success'))
        <div class="alert alert-success py-2 small">{{ session('success') }}</div>
    @endif

    <div class="ai-chat-page" id="aiChatPage">
        <div class="ai-side">
            <div class="ai-side-head">
                <form method="POST" action="{{ route('ai.conversations.store') }}" class="mb-2">
                    @csrf
                    <button class="btn btn-primary btn-sm w-100"><i class="fa fa-plus me-1"></i>新建会话</button>
                </form>
                <input type="text" id="convSearch" class="form-control form-control-sm" placeholder="搜索历史会话…">
            </div>
            <div class="ai-side-list" id="convList">
                @forelse ($conversations as $conv)
                    <div class="conv-item {{ $selected && $selected->id === $conv->id ? 'active' : '' }}"
                         data-conv-title="{{ mb_strtolower($conv->title ?? '') }}">
                        <div class="d-flex align-items-start gap-1">
                            <a href="{{ route('ai.chat', ['c' => $conv->id]) }}" class="flex-fill text-decoration-none" style="min-width:0;">
                                <div class="conv-title">{{ $conv->title ?: '新会话' }}</div>
                                <div class="conv-meta">
                                    <span>{{ $conv->messages_count ?? $conv->message_count ?? 0 }} 条</span>
                                    <span>{{ $conv->last_message_at?->format('m-d H:i') ?? $conv->created_at?->format('m-d H:i') }}</span>
                                </div>
                            </a>
                            <div class="dropdown">
                                <span class="conv-more" data-bs-toggle="dropdown" role="button"><i class="fa fa-ellipsis-v"></i></span>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#" data-rename="{{ $conv->id }}" data-title="{{ $conv->title }}">
                                            <i class="fa fa-pencil me-1"></i>重命名
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="{{ route('ai.conversations.export', $conv->id) }}">
                                            <i class="fa fa-download me-1"></i>导出 / 保存
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('ai.conversations.destroy', $conv->id) }}"
                                              onsubmit="return confirm('确定删除该会话及其全部附件吗？此操作不可恢复。');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="dropdown-item text-danger"><i class="fa fa-trash me-1"></i>删除会话</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-muted small text-center mt-4">暂无会话，点击上方「新建会话」开始</div>
                @endforelse
            </div>
        </div>

        <div class="ai-main">
            <div class="ai-drop-hint">松开即可添加附件</div>

            <div class="ai-main-head">
                <div class="flex-fill" style="min-width:180px;">
                    @php($activeProviderId = $selected ? $selected->provider_id : ($providers->first()?->id))
                    <div class="fw-bold">
                        {{ $selected?->title ?: '新会话' }}
                        @if ($selected?->skill_code)
                            @php($currentSkill = $skills->firstWhere('code', $selected->skill_code))
                            <span class="badge bg-info text-dark ms-1">技能：{{ $currentSkill?->name ?? $selected->skill_code }}</span>
                        @endif
                    </div>
                    <div class="small text-muted">
                        @if ($activeProviderId)
                            @php($currentProvider = $providers->firstWhere('id', $activeProviderId))
                            当前模型：{{ $currentProvider?->name ?? '已配置服务商' }} · {{ $currentProvider?->model ?? '' }}
                        @else
                            当前模型：内置业务问答（ERP 数据）
                        @endif
                    </div>
                </div>

                <select name="provider_id" form="aiSendForm" class="form-select form-select-sm" style="width:auto;max-width:240px;" title="选择本轮问答使用的模型">
                    <option value="" {{ ! $activeProviderId ? 'selected' : '' }}>内置业务问答（ERP 数据）</option>
                    @foreach ($providers as $provider)
                        <option value="{{ $provider->id }}" {{ (int) $activeProviderId === (int) $provider->id ? 'selected' : '' }}>
                            {{ $provider->name }} · {{ $provider->model }}
                        </option>
                    @endforeach
                </select>

                <select name="skill_code" form="aiSendForm" class="form-select form-select-sm" style="width:auto;max-width:200px;" title="选择 Agent / 技能">
                    <option value="">默认能力（全部技能）</option>
                    @foreach ($skills as $skill)
                        <option value="{{ $skill->code }}" {{ $selected?->skill_code === $skill->code ? 'selected' : '' }} title="{{ $skill->description }}">
                            {{ $skill->name }}@if($skill->type === 'agent')（智能体）@endif
                        </option>
                    @endforeach
                </select>

                @if ($selected)
                    <form method="POST" action="{{ route('ai.conversations.clear', $selected->id) }}"
                          onsubmit="return confirm('确定清空当前会话的全部消息吗？附件也会一并删除。');">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">清空</button>
                    </form>
                    <a href="{{ route('ai.conversations.export', $selected->id) }}" class="btn btn-sm btn-outline-secondary">导出 / 保存</a>
                @endif
            </div>

            <div class="ai-main-body" id="chatBox">
                @forelse ($messages as $message)
                    @php($isUser = $message->role === 'user')
                    <div class="ai-msg {{ $isUser ? 'user' : '' }}">
                        @if (! $isUser)
                            <div class="ai-avatar assistant"><i class="fa fa-robot"></i></div>
                        @endif
                        <div style="max-width:78%;">
                            @if ($message->attachments->isNotEmpty())
                                <div class="ai-attach {{ $isUser ? 'justify-content-end' : '' }}">
                                    @foreach ($message->attachments as $attachment)
                                        @if ($attachment->isImage())
                                            <a href="{{ route('ai.attachments.preview', $attachment->id) }}" target="_blank" class="ai-attach-item">
                                                <img src="{{ route('ai.attachments.preview', $attachment->id) }}" alt="{{ $attachment->file_name }}">
                                                <span class="text-truncate" style="max-width:120px;" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</span>
                                            </a>
                                        @else
                                            <a href="{{ route('ai.attachments.download', $attachment->id) }}" class="ai-attach-item">
                                                <i class="fa {{ $attachment->icon() }}"></i>
                                                <span class="text-truncate" style="max-width:140px;" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</span>
                                                <span class="text-muted">{{ $attachment->readableSize() }}</span>
                                            </a>
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                            <div class="ai-bubble {{ $isUser ? 'user' : 'assistant' }}">{{ $message->content }}</div>
                            <div class="msg-time {{ $isUser ? 'text-end' : '' }}">
                                @if (! $isUser && ($message->meta['model_name'] ?? null))
                                    <span class="badge bg-light text-muted border me-1">
                                        <i class="fa fa-microchip"></i> {{ $message->meta['model_name'] }}
                                    </span>
                                @endif
                                @if (! $isUser && ($message->meta['skill_name'] ?? null))
                                    <span class="badge bg-light text-muted border me-1">{{ $message->meta['skill_name'] }}</span>
                                @endif
                                @if (! $isUser && ! empty($message->meta['tools_used']))
                                    <span class="badge bg-light text-muted border me-1">
                                        <i class="fa fa-wrench"></i> 已调用 {{ count($message->meta['tools_used']) }} 个工具
                                    </span>
                                @endif
                                @if (! $isUser && ! empty($message->meta['ai_error']))
                                    <span class="badge bg-danger me-1">AI 异常</span>
                                @endif
                                {{ $message->created_at?->format('H:i') }}
                                @if (! $isUser)
                                    <span class="copy-btn" title="复制"><i class="fa fa-copy"></i> 复制</span>
                                @endif
                            </div>
                        </div>
                        @if ($isUser)
                            <div class="ai-avatar user">{{ mb_substr(auth()->user()?->name ?? '我', 0, 1) }}</div>
                        @endif
                    </div>
                @empty
                    <div class="ai-empty">
                        <i class="fa fa-comments-o fa-3x mb-3 d-block"></i>
                        <div>选择已启用的 AI 服务商后，会真实调用 DeepSeek / OpenAI 并执行受权限约束的查询工具；未选择服务商时使用内置业务问答。</div>
                        <div class="mt-3">
                            @foreach ($examples as $example)
                                <span class="ai-suggest" data-suggest="{{ $example }}">{{ $example }}</span>
                            @endforeach
                        </div>
                        <div class="mt-3 small">支持上传图片与文本 / 表格文件；可选择「技能 / Agent」限定问答范围，也可使用下方提问技巧。</div>
                        @if ($providers->isEmpty())
                            <div class="mt-2 small text-warning">
                                尚未配置 AI 服务商，当前只能使用内置业务问答。
                                @if (auth()->user()?->can('ai.provider.manage'))
                                    <a href="{{ route('ai.providers') }}">去添加 DeepSeek / OpenAI</a>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforelse
            </div>

            <form method="POST" action="{{ route('ai.chat.send') }}" enctype="multipart/form-data" class="ai-composer" id="aiSendForm">
                @csrf
                <input type="hidden" name="conversation_id" value="{{ $selected?->id }}">

                <div class="ai-chips" id="attachmentChips"></div>

                <div class="ai-tips">
                    <span class="small text-muted me-1">提问技巧：</span>
                    @foreach ($promptTips as $tip)
                        <button type="button" class="ai-tip" data-tip="{{ $tip['text'] }}">{{ $tip['label'] }}</button>
                    @endforeach
                </div>

                <textarea name="content" id="qaContent" class="form-control mb-2" rows="2"
                          placeholder="输入你的问题，如：本月销售额多少？ACC-RM01 还有多少库存？">{{ old('content') }}</textarea>

                <div class="ai-tools">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnImage">
                        <i class="fa fa-image me-1"></i>添加图片
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary me-auto" id="btnFile">
                        <i class="fa fa-paperclip me-1"></i>添加文件
                    </button>
                    <span class="small text-muted me-2">已选择 <b id="attachCount">0</b> 个附件</span>
                    <button class="btn btn-primary btn-sm" id="sendBtn">发送并保存</button>
                </div>
                <input type="file" name="images[]" id="imageInput" accept="image/*" multiple class="d-none">
                <input type="file" name="files[]" id="fileInput" multiple class="d-none">
                @if ($errors->any())
                    <div class="text-danger small mt-2">{{ $errors->first() }}</div>
                @endif
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var page = document.getElementById('aiChatPage');
    var box = document.getElementById('chatBox');
    var input = document.getElementById('qaContent');
    var imageInput = document.getElementById('imageInput');
    var fileInput = document.getElementById('fileInput');
    var chips = document.getElementById('attachmentChips');
    var countEl = document.getElementById('attachCount');

    function scrollBottom() {
        if (box) box.scrollTop = box.scrollHeight;
    }
    scrollBottom();

    function fileLabel(file) {
        return file.name + (file.size ? ' · ' + (file.size > 1048576 ? (file.size / 1048576).toFixed(1) + ' MB' : Math.round(file.size / 1024) + ' KB') : '');
    }

    function renderChips() {
        chips.innerHTML = '';
        var total = 0;
        [[imageInput, '图片'], [fileInput, '文件']].forEach(function (pair) {
            var el = pair[0];
            if (!el || !el.files) return;
            Array.prototype.forEach.call(el.files, function (file) {
                total++;
                var chip = document.createElement('span');
                chip.className = 'ai-chip';
                chip.innerHTML = '<i class="fa ' + (pair[1] === '图片' ? 'fa-image' : 'fa-paperclip') + '"></i>'
                    + '<span class="text-truncate" style="max-width:200px;"></span>'
                    + '<i class="fa fa-times remove" title="移除全部' + pair[1] + '"></i>';
                chip.querySelector('span').textContent = fileLabel(file);
                chip.querySelector('.remove').addEventListener('click', function () {
                    el.value = '';
                    renderChips();
                });
                chips.appendChild(chip);
            });
        });
        countEl.textContent = total;
    }

    if (imageInput) imageInput.addEventListener('change', renderChips);
    if (fileInput) fileInput.addEventListener('change', renderChips);
    document.getElementById('btnImage')?.addEventListener('click', function () { imageInput.click(); });
    document.getElementById('btnFile')?.addEventListener('click', function () { fileInput.click(); });

    document.querySelectorAll('[data-suggest], [data-tip]').forEach(function (el) {
        el.addEventListener('click', function () {
            input.value = el.dataset.suggest || el.dataset.tip || '';
            input.focus();
        });
    });

    // 拖拽上传：图片进图片输入，其余进文件输入
    if (page) {
        ['dragenter', 'dragover'].forEach(function (name) {
            page.addEventListener(name, function (e) { e.preventDefault(); page.classList.add('dragging'); });
        });
        ['dragleave', 'drop'].forEach(function (name) {
            page.addEventListener(name, function (e) {
                e.preventDefault();
                if (name === 'dragleave' && e.relatedTarget && page.contains(e.relatedTarget)) return;
                page.classList.remove('dragging');
            });
        });
        page.addEventListener('drop', function (e) {
            var files = e.dataTransfer.files;
            if (!files || !files.length) return;
            var dtImages = new DataTransfer();
            var dtFiles = new DataTransfer();
            Array.prototype.forEach.call(files, function (file) {
                if (file.type && file.type.indexOf('image/') === 0) dtImages.items.add(file);
                else dtFiles.items.add(file);
            });
            if (dtImages.files.length) imageInput.files = dtImages.files;
            if (dtFiles.files.length) fileInput.files = dtFiles.files;
            renderChips();
        });
    }

    // 粘贴图片到输入框
    if (input) {
        input.addEventListener('paste', function (e) {
            var items = (e.clipboardData || {}).items || [];
            var dt = new DataTransfer();
            Array.prototype.forEach.call(items, function (item) {
                if (item.type && item.type.indexOf('image/') === 0) {
                    var file = item.getAsFile();
                    if (file) dt.items.add(file);
                }
            });
            if (dt.files.length) {
                imageInput.files = dt.files;
                renderChips();
            }
        });
    }

    document.querySelectorAll('[data-rename]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            var id = el.dataset.rename;
            var title = prompt('输入新的会话名称：', el.dataset.title || '');
            if (title === null) return;
            title = title.trim();
            if (!title) return;
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ url('/ai/conversations') }}' + '/' + id;
            var token = document.createElement('input');
            token.name = '_token'; token.value = '{{ csrf_token() }}';
            var method = document.createElement('input');
            method.name = '_method'; method.value = 'PATCH';
            var titleInput = document.createElement('input');
            titleInput.name = 'title'; titleInput.value = title;
            form.appendChild(token); form.appendChild(method); form.appendChild(titleInput);
            document.body.appendChild(form);
            form.submit();
        });
    });

    var search = document.getElementById('convSearch');
    if (search) {
        search.addEventListener('input', function () {
            var q = this.value.trim().toLowerCase();
            document.querySelectorAll('#convList .conv-item').forEach(function (item) {
                item.style.display = (item.dataset.convTitle || '').indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.copy-btn').forEach(function (el) {
        el.addEventListener('click', function () {
            var bubble = el.closest('.ai-msg')?.querySelector('.ai-bubble');
            if (!bubble) return;
            navigator.clipboard?.writeText(bubble.textContent || '').then(function () {
                el.innerHTML = '<i class="fa fa-check"></i> 已复制';
                setTimeout(function () { el.innerHTML = '<i class="fa fa-copy"></i> 复制'; }, 1500);
            });
        });
    });
});
</script>
@endpush
