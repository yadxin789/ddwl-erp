@extends('layouts.admin')

@section('title', '项目电话会议')

@section('content')
@include('rnd.partials.project-tabs', ['activeTab' => 'voice'])

@if (session('success'))
<div class="alert alert-success py-2">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-telephone me-1"></i>项目电话会议 · {{ $project->project_name }}</span>
        <span class="text-muted small">WebRTC 直连 + 本机信令，断网 / 内网可用</span>
    </div>
    <div class="card-body">
        @if (! $meeting)
        <div class="text-center py-4">
            <p class="text-muted mb-3">当前没有进行中的会议。</p>
            <form method="POST" action="{{ route('rnd.projects.voice.start', $project) }}">
                @csrf
                <input type="hidden" name="title" value="项目电话会议 · {{ $project->project_name }}">
                <button class="btn btn-primary">开启会议</button>
            </form>
        </div>
        @else
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div class="fw-semibold">{{ $meeting->title }}</div>
                <div class="text-muted small">由 {{ $meeting->creator->identity_label ?? '—' }} 于 {{ $meeting->started_at?->format('Y-m-d H:i') }} 开启</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-success" id="joinBtn">加入会议</button>
                <button class="btn btn-outline-danger d-none" id="leaveBtn">挂断</button>
                <form method="POST" action="{{ route('rnd.projects.voice.close', $project) }}" class="d-inline">
                    @csrf
                    <button class="btn btn-outline-secondary">结束会议</button>
                </form>
            </div>
        </div>

        <div id="voiceHint" class="alert alert-info py-2 small">
            点击"加入会议"后请求麦克风权限；同一内网内可直接建立点对点音频，无需外网。
        </div>

        <div class="row g-2" id="memberGrid"></div>

        <div class="mt-3 small text-muted" id="diag"></div>

        <div id="remoteAudio"></div>
        @endif
    </div>
</div>

@if ($meeting)
<script>
(function () {
    var myId = {{ (int) auth()->id() }};
    var joinUrl = @json(route('rnd.projects.voice.join', $project));
    var stateUrl = @json(route('rnd.projects.voice.state', $project));
    var signalUrl = @json(route('rnd.projects.voice.signal', $project));
    var leaveUrl = @json(route('rnd.projects.voice.leave', $project));
    var csrf = @json(csrf_token());

    var pcs = {};
    var localStream = null;
    var joined = false;
    var timer = null;

    var grid = document.getElementById('memberGrid');
    var hint = document.getElementById('voiceHint');
    var diag = document.getElementById('diag');
    var joinBtn = document.getElementById('joinBtn');
    var leaveBtn = document.getElementById('leaveBtn');

    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify(body || {})
        });
    }
    function getJson(url) {
        return fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
    }

    function renderMembers(members) {
        grid.innerHTML = '';
        members.forEach(function (m) {
            var conn = m.mine ? '本机' : (pcs[m.user_id] ? '已连接' : '待连接');
            var el = document.createElement('div');
            el.className = 'col-6 col-md-3 col-lg-2';
            el.innerHTML =
                '<div class="card text-center h-100' + (m.mine ? ' border-primary' : '') + '">' +
                '<div class="card-body p-2">' +
                '<div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center mb-2" style="width:44px;height:44px;">' +
                (m.name || '?').substr(0, 1) + '</div>' +
                '<div class="small fw-semibold text-truncate">' + m.name + '</div>' +
                '<div class="small text-muted">' + conn + '</div>' +
                '</div></div>';
            grid.appendChild(el);
        });
    }

    async function createPc(peerId) {
        var pc = new RTCPeerConnection({ iceServers: [] }); // 局域网直连，不依赖 STUN/TURN
        pcs[peerId] = pc;
        if (localStream) {
            localStream.getTracks().forEach(function (t) { pc.addTrack(t, localStream); });
        }
        pc.onicecandidate = function (e) {
            if (e.candidate) { post(signalUrl, { type: 'candidate', to: peerId, payload: JSON.stringify(e.candidate) }); }
        };
        pc.ontrack = function (e) {
            var audio = document.createElement('audio');
            audio.autoplay = true;
            audio.srcObject = e.streams[0];
            audio.id = 'audio-' + peerId;
            var old = document.getElementById('audio-' + peerId);
            if (old) { old.remove(); }
            document.getElementById('remoteAudio').appendChild(audio);
        };
        pc.onconnectionstatechange = function () {
            if (['failed', 'closed', 'disconnected'].indexOf(pc.connectionState) >= 0) {
                delete pcs[peerId];
            }
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
                if (pcs[s.from]) { await pcs[s.from].setRemoteDescription(JSON.parse(s.payload)); }
            } else if (s.type === 'candidate') {
                if (pcs[s.from]) { await pcs[s.from].addIceCandidate(JSON.parse(s.payload)); }
            } else if (s.type === 'leave') {
                if (pcs[s.from]) { pcs[s.from].close(); delete pcs[s.from]; }
                var a = document.getElementById('audio-' + s.from);
                if (a) { a.remove(); }
            }
        } catch (err) {
            diag.textContent = '信令处理异常：' + err.message;
        }
    }

    async function tick() {
        var d;
        try {
            var r = await getJson(stateUrl);
            if (!r.ok) { return; }
            d = await r.json();
        } catch (e) { return; }

        renderMembers(d.members || []);

        for (var i = 0; i < (d.signals || []).length; i++) {
            await handleSignal(d.signals[i]);
        }

        // 小号发起 offer，避免双向同时协商
        for (var j = 0; j < (d.members || []).length; j++) {
            var m = d.members[j];
            if (m.mine || pcs[m.user_id]) { continue; }
            if (myId < m.user_id) {
                try {
                    var pc = await createPc(m.user_id);
                    var offer = await pc.createOffer();
                    await pc.setLocalDescription(offer);
                    await post(signalUrl, { type: 'offer', to: m.user_id, payload: JSON.stringify(offer) });
                } catch (err) { diag.textContent = '发起连接失败：' + err.message; }
            }
        }
        diag.textContent = '在线成员 ' + (d.members || []).length + ' 人 · 已建立连接 ' + Object.keys(pcs).length + ' 路';
    }

    joinBtn.addEventListener('click', async function () {
        if (joined) { return; }
        try {
            localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
        } catch (err) {
            hint.className = 'alert alert-warning py-2 small';
            hint.textContent = '无法获取麦克风：' + err.message + '。请允许麦克风权限；若通过局域网 http 访问，需使用 https 或浏览器允许不安全来源。';
            return;
        }
        await post(joinUrl, { peer_id: String(myId) });
        joined = true;
        joinBtn.classList.add('d-none');
        leaveBtn.classList.remove('d-none');
        hint.className = 'alert alert-success py-2 small';
        hint.textContent = '已加入会议，正在与在线成员建立音频连接…';
        timer = setInterval(tick, 2000);
        tick();
    });

    leaveBtn.addEventListener('click', async function () {
        if (timer) { clearInterval(timer); }
        Object.keys(pcs).forEach(function (k) { pcs[k].close(); });
        pcs = {};
        if (localStream) { localStream.getTracks().forEach(function (t) { t.stop(); }); }
        try { await post(leaveUrl, {}); } catch (e) { }
        joined = false;
        joinBtn.classList.remove('d-none');
        leaveBtn.classList.add('d-none');
        hint.className = 'alert alert-info py-2 small';
        hint.textContent = '已挂断。';
    });

    // 进页面先拉一次成员状态（只读，不申请麦克风）
    getJson(stateUrl).then(function (r) { return r.ok ? r.json() : null; }).then(function (d) {
        if (d) { renderMembers(d.members || []); }
    }).catch(function () { });
})();
</script>
@endif
@endsection
