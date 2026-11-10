@extends('layouts.admin')

@section('title', '平台API日志')

@section('content')
<div class="card">
    <div class="card-header">第三方平台 API 调用日志</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>平台</th><th>接口</th><th>方法</th><th>HTTP</th><th>耗时</th><th>时间</th></tr></thead>
        <tbody>
            @forelse ($logs as $l)
            <tr>
                <td><span class="badge bg-info">{{ $l->platform }}</span></td>
                <td class="font-monospace small">{{ $l->api_path }}</td>
                <td>{{ $l->method }}</td>
                <td>@if($l->http_code < 300)<span class="badge bg-success">{{ $l->http_code }}</span>@else<span class="badge bg-danger">{{ $l->http_code }}</span>@endif</td>
                <td>{{ $l->duration_ms }}ms</td>
                <td class="small">{{ $l->called_at?->format('m-d H:i:s') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无 API 调用记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $logs->links() }}</div>
</div>
@endsection
