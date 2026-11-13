@extends('layouts.admin')

@section('title', '项目电话会议')

@section('content')
@include('rnd.partials.project-tabs', ['project' => $project, 'activeTab' => 'voice'])

@if (session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

@if (! $meeting)
<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-telephone" style="font-size:42px;color:#c5c9d4;"></i>
        <p class="text-muted mt-3 mb-3">当前没有进行中的会议。</p>
        <form method="POST" action="{{ route('rnd.projects.voice.start', $project) }}">
            @csrf
            <input type="hidden" name="title" value="项目电话会议 · {{ $project->project_name }}">
            <button class="btn btn-primary"><i class="bi bi-telephone-plus me-1"></i>开启会议</button>
        </form>
    </div>
</div>
@else
<div class="voice-shell">
    <div class="voice-sidebar">
        <div class="p-3 border-bottom bg-white">
            <div class="fw-semibold text-truncate"><i class="bi bi-telephone text-primary me-1"></i>电话会议</div>
            <div class="small mt-1">
                @if ($isHost)
                    <span class="badge bg-success"><i class="bi bi-star-fill me-1"></i>你是主持人</span>
                @else
                    <span class="badge bg-secondary">听众</span>
                @endif
            </div>
            <div class="text-muted small mt-2">{{ $meeting->title }}</div>
        </div>
        <div class="px-3 py-2 border-bottom small text-muted">项目成员</div>
        <div class="flex-grow-1 overflow-y-auto" id="voice-member-list">
            @foreach ($members as $member)
                <div class="voice-member" data-uid="{{ $member['id'] }}" data-host="{{ $member['is_host'] ? 1 : 0 }}">
                    <span class="voice-avatar-wrap">
                        @if ($member['avatar_url'])
                            <img src="{{ $member['avatar_url'] }}" class="voice-avatar-img" alt="{{ $member['name'] }}">
                        @else
                            <span class="voice-avatar">{{ $member['initial'] }}</span>
                        @endif
                        <span class="online-dot offline"></span>
                    </span>
                    <span class="min-w-0">
                        <span class="d-block text-truncate small fw-semibold">{{ $member['name'] }} @if($member['is_me'])<span class="text-muted">(我)</span>@endif</span>
                        <span class="d-block small text-muted">
                            @if ($member['is_host'])<span class="badge bg-success py-0">主持人</span>@endif
                            <span class="online-label">离线</span>
                        </span>
                    </span>
                    <span class="presenter-tag d-none">演示中</span>
                </div>
            @endforeach
        </div>
        <div class="p-2 border-top bg-white">
            <button id="btn-apply-presenter" class="btn btn-outline-primary btn-sm w-100" {{ $isHost ? 'disabled' : '' }}>
                <i class="bi bi-hand-index-thumb me-1"></i>申请演示
            </button>
        </div>
    </div>

    <div class="voice-main">
        <div class="voice-toolbar">
            <div class="min-w-0">
                <span class="small text-muted">当前演示者：</span>
                <span id="current-presenter-name" class="fw-bold">{{ $presenter->name ?? '未开始' }}</span>
                <span id="apply-banner" class="alert alert-warning py-1 px-2 mb-0 ms-2 d-none"></span>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <select id="doc-select" class="form-select form-select-sm" style="width:240px">
                    <option value="">-- 选择演示文档 --</option>
                    @foreach ($docs as $doc)
                        <option value="{{ $doc->id }}" @selected((int) $meeting->current_document_id === (int) $doc->id)>[项目] {{ $doc->title }}</option>
                    @endforeach
                </select>
                <a href="{{ route('rnd.projects.collab.create', $project) }}" class="btn btn-sm btn-outline-secondary" title="到协同办公新建/上传文档">
                    <i class="bi bi-upload"></i> 上传
                </a>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="edit-mode">
                    <label class="form-check-label small" for="edit-mode">编辑模式</label>
                </div>
                <a id="btn-download" class="btn btn-sm btn-outline-secondary disabled" aria-disabled="true">
                    <i class="bi bi-download"></i> 下载
                </a>
                @if ($isHost)
                    <button id="btn-revoke-presenter" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i>收回演示权
                    </button>
                @endif
            </div>
        </div>

        <div class="voice-doc-wrap" id="doc-frame-wrap">
            <div class="text-center text-muted" id="doc-empty-tip">
                <i class="bi bi-file-earmark-text" style="font-size:48px;"></i>
                <p class="mt-2">主持人为你选择一篇文档后，这里将展示协同办公文档内容</p>
            </div>
            <div id="doc-viewer" class="d-none w-100 h-100 d-flex flex-column">
                <div class="px-3 py-2 border-bottom bg-white d-flex justify-content-between align-items-center">
                    <span class="fw-semibold" id="doc-title"></span>
                    <span class="small text-muted" id="doc-meta"></span>
                </div>
                <div class="flex-grow-1 overflow-auto bg-white p-4">
                    <pre id="doc-content" class="mb-0" style="white-space:pre-wrap;font-family:inherit;"></pre>
                    <textarea id="doc-editor" class="form-control d-none" rows="18" style="font-family:ui-monospace,monospace;"></textarea>
                    <div class="mt-2 d-none" id="doc-save-wrap">
                        <button class="btn btn-sm btn-primary" id="doc-save-btn"><i class="bi bi-check2 me-1"></i>保存演示文档</button>
                        <span class="small text-muted ms-2" id="doc-save-tip"></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="voice-controls">
            <button id="btn-mic" class="btn btn-secondary" disabled><i class="bi bi-mic"></i> 静音</button>
            <button id="btn-call" class="btn btn-primary"><i class="bi bi-telephone me-1"></i>加入会议</button>
            <button id="btn-follow" class="btn btn-outline-secondary" disabled><i class="bi bi-bullseye me-1"></i>回到演示</button>
            <button id="btn-hangup" class="btn btn-outline-danger d-none"><i class="bi bi-telephone-x me-1"></i>挂断</button>
            @if ($isHost)
                <form method="POST" action="{{ route('rnd.projects.voice.close', $project) }}" class="d-inline" onsubmit="return confirm('确定结束本次会议吗？')">
                    @csrf
                    <button class="btn btn-outline-secondary">结束会议</button>
                </form>
            @endif
        </div>
    </div>
</div>

<div id="remoteAudio"></div>

@push('styles')
<style>
    .voice-shell { display: flex; height: calc(100vh - 230px); min-height: 540px; border: 1px solid #dee2e6; border-radius: .25rem; background: #fff; overflow: hidden; }
    .voice-sidebar { width: 230px; min-width: 230px; border-right: 1px solid #dee2e6; background: #f8f9fa; display: flex; flex-direction: column; }
    .voice-member { display: flex; align-items: center; gap: 9px; padding: 9px 12px; border-bottom: 1px solid #eef0f3; }
    .voice-avatar-wrap { position: relative; flex: 0 0 auto; }
    .voice-avatar, .voice-avatar-img { width: 36px; height: 36px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; background: #6c757d; color: #fff; font-size: 15px; object-fit: cover; }
    .online-dot { position: absolute; right: 0; bottom: 0; width: 9px; height: 9px; border-radius: 50%; border: 2px solid #fff; }
    .online-dot.online { background: #4caf50; }
    .online-dot.offline { background: #bdbdbd; }
    .presenter-tag { margin-left: auto; font-size: 10px; color: #0d6efd; background: #e7f1ff; border-radius: 3px; padding: 1px 4px; white-space: nowrap; }
    .voice-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
    .voice-toolbar { padding: 8px 12px; border-bottom: 1px solid #dee2e6; background: #fff; display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
    .voice-doc-wrap { flex: 1; background: #f5f5f5; display: flex; align-items: center; justify-content: center; min-height: 0; }
    .voice-controls { padding: 10px 12px; border-top: 1px solid #dee2e6; background: #fff; display: flex; justify-content: center; gap: 10px; flex-wrap: wrap; }
    .min-w-0 { min-width: 0; }
    @media (max-width: 767.98px) { .voice-shell { flex-direction: column; height: auto; } .voice-sidebar { width: 100%; min-width: 0; max-height: 240px; } }
</style>
@endpush

@push('scripts')
<script>
(function () {
    var myId = {{ (int) auth()->id() }};
    var isHost = @json($isHost);
    var projectId = {{ (int) $project->id }};
    var joinUrl = @json(route('rnd.projects.voice.join', $project));
    var stateUrl = @json(route('rnd.projects.voice.state', $project));
    var statePeekUrl = @json(route('rnd.projects.voice.state', ['project' => $project, 'peek' => 1]));
    var signalUrl = @json(route('rnd.projects.voice.signal', $project));
    var leaveUrl = @json(route('rnd.projects.voice.leave', $project));
    var applyUrl = @json(route('rnd.projects.voice.apply', $project));
    var presenterUrl = @json(route('rnd.projects.voice.presenter', $project));
    var documentUrl = @json(route('rnd.projects.voice.document', $project));
    var docBase = @json(url('/rnd/projects/' . $project->id . '/voice/document'));
    var collabSaveBase = @json(url('/rnd/collab'));
    var csrf = @json(csrf_token());
    var currentPresenterId = @json($meeting->current_presenter_id);
    var currentDocId = @json($meeting->current_document_id);

    var pcs = {};
    var localStream = null;
    var joined = false;
    var timer = null;
    var following = true;

    var diag = null;
    var joinBtn = document.getElementById('btn-call');
    var hangupBtn = document.getElementById('btn-hangup');
    var micBtn = document.getElementById('btn-mic');
    var micOn = true;

    function post(url, body) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        });
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    }

    function renderMembers(online) {
        var ids = {};
        (online || []).forEach(function (m) { ids[m.user_id] = true; });
        document.querySelectorAll('#voice-member-list .voice-member').forEach(function (el) {
            var uid = parseInt(el.dataset.uid, 10);
            var isOnline = !!ids[uid];
            var dot = el.querySelector('.online-dot');
            dot.classList.toggle('online', isOnline);
            dot.classList.toggle('offline', !isOnline);
            el.querySelector('.online-label').textContent = isOnline ? '在线' : '离线';
            el.querySelector('.presenter-tag').classList.toggle('d-none', uid !== currentPresenterId);
        });
    }

    function updatePresenter(id, name) {
        currentPresenterId = id;
        document.getElementById('current-presenter-name').textContent = name || '未开始';
        renderMembers(lastOnline);
    }

    var lastOnline = [];
    function updateDocMeta(doc) {
        var dl = document.getElementById('btn-download');
        if (doc) {
            dl.classList.remove('disabled');
            dl.removeAttribute('aria-disabled');
            dl.href = doc.download_url;
            document.getElementById('doc-title').textContent = doc.title;
            document.getElementById('doc-meta').textContent = (doc.type || '在线文档') + ' · v' + (doc.version || 1);
        } else {
            dl.classList.add('disabled');
            dl.setAttribute('aria-disabled', 'true');
            dl.removeAttribute('href');
        }
    }

    function applyEditMode(edit) {
        var canEdit = (isHost || currentPresenterId === myId) && edit;
        document.getElementById('edit-mode').checked = !!edit;
        document.getElementById('doc-content').classList.toggle('d-none', canEdit);
        document.getElementById('doc-editor').classList.toggle('d-none', !canEdit);
        document.getElementById('doc-save-wrap').classList.toggle('d-none', !canEdit);
        if (canEdit) {
            document.getElementById('doc-editor').value = document.getElementById('doc-content').textContent;
        }
    }

    function loadDoc(id) {
        if (!id) {
            currentDocId = null;
            document.getElementById('doc-viewer').classList.add('d-none');
            document.getElementById('doc-empty-tip').classList.remove('d-none');
            updateDocMeta(null);
            return;
        }
        currentDocId = id;
        getJson(docBase + '/' + id).then(function (r) { return r.ok ? r.json() : null; }).then(function (doc) {
            if (!doc) return;
            document.getElementById('doc-empty-tip').classList.add('d-none');
            document.getElementById('doc-viewer').classList.remove('d-none');
            document.getElementById('doc-content').textContent = doc.content || '';
            document.getElementById('doc-title').textContent = doc.title;
            document.getElementById('doc-meta').textContent = (doc.type || '在线文档');
            updateDocMeta({ title: doc.title, type: doc.type, version: 1, download_url: doc.download_url });
            applyEditMode(document.getElementById('edit-mode').checked);
        });
    }

    async function createPc(peerId) {
        var pc = new RTCPeerConnection({ iceServers: [] });
        pcs[peerId] = pc;
        if (localStream) { localStream.getTracks().forEach(function (t) { pc.addTrack(t, localStream); }); }
        pc.onicecandidate = function (e) {
            if (e.candidate) { post(signalUrl, { type: 'candidate', to: peerId, payload: JSON.stringify(e.candidate) }); }
        };
        pc.ontrack = function (e) {
            var old = document.getElementById('audio-' + peerId);
            if (old) old.remove();
            var audio = document.createElement('audio');
            audio.autoplay = true;
            audio.srcObject = e.streams[0];
            audio.id = 'audio-' + peerId;
            document.getElementById('remoteAudio').appendChild(audio);
        };
        pc.onconnectionstatechange = function () {
            if (['failed', 'closed', 'disconnected'].indexOf(pc.connectionState) >= 0) { delete pcs[peerId]; }
        };
        return pc;
    }

    async function handleSignal(s) {
        try {
            if (s.type === 'offer') {
                var pc = pcs[s.from] || await createPc(s.from);
                await pc.setRemoteDescription(JSON.parse(s.payload));
                var answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await post(signalUrl, { type: 'answer', to: s.from, payload: JSON.stringify(answer) });
            } else if (s.type === 'answer') {
                if (pcs[s.from]) await pcs[s.from].setRemoteDescription(JSON.parse(s.payload));
            } else if (s.type === 'candidate') {
                if (pcs[s.from]) await pcs[s.from].addIceCandidate(JSON.parse(s.payload));
            } else if (s.type === 'leave') {
                if (pcs[s.from]) { pcs[s.from].close(); delete pcs[s.from]; }
                var a = document.getElementById('audio-' + s.from);
                if (a) a.remove();
            } else if (s.type === 'presenter_apply' && isHost) {
                var payload = {};
                try { payload = JSON.parse(s.payload || '{}'); } catch (e) {}
                var banner = document.getElementById('apply-banner');
                banner.classList.remove('d-none');
                banner.textContent = (payload.name || '成员') + ' 申请演示 ';
                var approve = document.createElement('button');
                approve.className = 'btn btn-sm btn-success py-0 ms-2';
                approve.textContent = '同意';
                approve.onclick = function () {
                    post(presenterUrl, { user_id: parseInt(s.from, 10) }).then(function () { banner.classList.add('d-none'); });
                };
                banner.appendChild(approve);
            } else if (s.type === 'presenter_changed') {
                var p = {};
                try { p = JSON.parse(s.payload || '{}'); } catch (e) {}
                var pid = p.presenter_id;
                var name = '未开始';
                if (pid) {
                    var member = document.querySelector('#voice-member-list .voice-member[data-uid="' + pid + '"] .fw-semibold');
                    if (member) name = member.textContent.replace('(我)', '').trim();
                }
                updatePresenter(pid, name);
            } else if (s.type === 'doc_selected') {
                var d = {};
                try { d = JSON.parse(s.payload || '{}'); } catch (e) {}
                document.getElementById('edit-mode').checked = !!d.edit_mode;
                loadDoc(d.document_id);
            }
        } catch (err) {
            if (diag) diag.textContent = '信令处理异常：' + err.message;
        }
    }

    async function tick() {
        var d;
        try {
            var r = await getJson(stateUrl);
            if (!r.ok) return;
            d = await r.json();
        } catch (e) { return; }

        lastOnline = d.members || [];
        renderMembers(lastOnline);
        if (d.presenter) {
            updatePresenter(d.presenter.id, d.presenter.name);
        } else {
            updatePresenter(null, '未开始');
        }
        if (d.document && d.document.id !== currentDocId) {
            document.getElementById('doc-select').value = String(d.document.id);
            loadDoc(d.document.id);
        } else if (!d.document && currentDocId) {
            document.getElementById('doc-select').value = '';
            loadDoc(null);
        }
        document.getElementById('edit-mode').checked = !!d.edit_mode;

        for (var i = 0; i < (d.signals || []).length; i++) {
            await handleSignal(d.signals[i]);
        }

        for (var j = 0; j < (d.members || []).length; j++) {
            var m = d.members[j];
            if (m.mine || pcs[m.user_id]) continue;
            if (myId < m.user_id) {
                try {
                    var pc = await createPc(m.user_id);
                    var offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    await post(signalUrl, { type: 'offer', to: m.user_id, payload: JSON.stringify(offer) });
                } catch (err) { if (diag) diag.textContent = '发起连接失败：' + err.message; }
            }
        }
        if (joined) {
            document.getElementById('btn-mic').disabled = false;
        }
    }

    joinBtn.addEventListener('click', async function () {
        if (joined) return;
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (err) {
            alert('无法获取麦克风：' + err.message + '。请允许麦克风权限；若通过局域网 http 访问，需使用 https。');
            return;
        }
        await post(joinUrl, { peer_id: String(myId) });
        joined = true;
        joinBtn.classList.add('d-none');
        hangupBtn.classList.remove('d-none');
        micBtn.disabled = false;
        document.getElementById('btn-follow').disabled = false;
        timer = setInterval(tick, 2000);
        tick();
    });

    hangupBtn.addEventListener('click', async function () {
        if (timer) clearInterval(timer);
        Object.keys(pcs).forEach(function (k) { pcs[k].close(); });
        pcs = {};
        if (localStream) localStream.getTracks().forEach(function (t) { t.stop(); });
        try { await post(leaveUrl, {}); } catch (e) {}
        joined = false;
        joinBtn.classList.remove('d-none');
        hangupBtn.classList.add('d-none');
        micBtn.disabled = true;
        document.getElementById('btn-follow').disabled = true;
    });

    micBtn.addEventListener('click', function () {
        if (!localStream) return;
        micOn = !micOn;
        localStream.getAudioTracks().forEach(function (t) { t.enabled = micOn; });
        micBtn.innerHTML = micOn ? '<i class="bi bi-mic"></i> 静音' : '<i class="bi bi-mic-mute"></i> 取消静音';
    });

    document.getElementById('btn-apply-presenter').addEventListener('click', function () {
        post(applyUrl, {}).then(function (r) {
            alert(r.ok ? '已向主持人申请演示' : '申请失败，请稍后重试');
        });
    });

    document.getElementById('btn-revoke-presenter')?.addEventListener('click', function () {
        post(presenterUrl, { user_id: null }).then(function () { /* 状态由轮询刷新 */ });
    });

    document.getElementById('doc-select').addEventListener('change', function () {
        var id = this.value ? parseInt(this.value, 10) : null;
        if (!isHost && currentPresenterId !== myId) {
            alert('只有主持人或当前演示者可以切换演示文档');
            this.value = currentDocId || '';
            return;
        }
        post(documentUrl, { doc_id: id, edit_mode: document.getElementById('edit-mode').checked }).then(function () { loadDoc(id); });
    });

    document.getElementById('edit-mode').addEventListener('change', function () {
        if (!isHost && currentPresenterId !== myId) {
            this.checked = false;
            alert('只有主持人或当前演示者可以切换编辑模式');
            return;
        }
        post(documentUrl, { doc_id: currentDocId, edit_mode: this.checked });
        applyEditMode(this.checked);
    });

    document.getElementById('doc-save-btn')?.addEventListener('click', function () {
        if (!currentDocId) return;
        var content = document.getElementById('doc-editor').value;
        var body = new FormData();
        body.append('_token', csrf);
        body.append('title', document.getElementById('doc-title').textContent);
        body.append('content', content);
        fetch(collabSaveBase + '/' + currentDocId + '/save', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function () {
                document.getElementById('doc-content').textContent = content;
                document.getElementById('doc-save-tip').textContent = '已保存';
            });
    });

    document.getElementById('btn-follow').addEventListener('click', function () {
        following = true;
        if (currentDocId) loadDoc(currentDocId);
        document.getElementById('doc-viewer').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });

    // 首次只读拉取在线状态与当前演示文档
    getJson(statePeekUrl).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
        if (!d) return;
        lastOnline = d.members || [];
        renderMembers(lastOnline);
        if (d.presenter) updatePresenter(d.presenter.id, d.presenter.name);
        if (d.document) {
            document.getElementById('doc-select').value = String(d.document.id);
            document.getElementById('edit-mode').checked = !!d.edit_mode;
            loadDoc(d.document.id);
        }
    }).catch(function () {});
})();
</script>
@endpush
@endif
@endsection
