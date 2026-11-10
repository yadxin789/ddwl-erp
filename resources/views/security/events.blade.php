@extends('layouts.admin')

@section('title', '安全事件')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <ul class="nav nav-tabs card-header-tabs mb-0">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'login' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'login']) }}">登录日志</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'events' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'events']) }}">安全事件</a>
            </li>
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>事件类型</th><th>用户</th><th>级别</th><th>详情</th><th>IP</th><th>时间</th></tr></thead>
            <tbody>
                @forelse ($items as $ev)
                <tr>
                    <td>{{ $ev->id }}</td>
                    <td><span class="badge bg-light text-dark">{{ $ev->event_type }}</span></td>
                    <td>{{ $ev->user->name ?? '系统' }}</td>
                    <td>
                        @php $lv = ['low'=>'secondary','medium'=>'warning','high'=>'danger','critical'=>'danger'][$ev->level] ?? 'secondary'; @endphp
                        <span class="badge bg-{{ $lv }}">{{ $ev->level }}</span>
                    </td>
                    <td class="small">{{ $ev->detail }}</td>
                    <td class="font-monospace small">{{ $ev->ip_address }}</td>
                    <td class="small">{{ $ev->created_at?->format('Y-m-d H:i:s') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">暂无安全事件</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
