@extends('layouts.admin')

@section('title', '安全审计')

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
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'operations' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'operations']) }}">操作日志</a>
            </li>
        </ul>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light"><tr><th>#</th><th>账号</th><th>IP</th><th>状态</th><th>原因</th><th>时间</th></tr></thead>
            <tbody>
                @forelse ($items as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->username }}</td>
                    <td class="font-monospace">{{ $log->ip_address }}</td>
                    <td>
                        @if($log->status === 'success')
                            <span class="badge bg-success">成功</span>
                        @else
                            <span class="badge bg-danger">失败</span>
                        @endif
                    </td>
                    <td class="small text-muted">{{ $log->fail_reason ?? '-' }}</td>
                    <td class="small">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">暂无登录日志</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
