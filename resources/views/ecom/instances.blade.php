@extends('layouts.admin')

@section('title', '平台设置')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>平台设置（{{ $instances->count() }} 个实例）</span>
        <div class="d-flex gap-2 align-items-center">
            @if ($expiring > 0)<span class="badge bg-warning text-dark">{{ $expiring }} 个授权 7 天内到期</span>@endif
            <a href="{{ route('ecom.onboarding') }}" class="btn btn-sm btn-primary">接入新平台</a>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr><th>实例</th><th>平台</th><th>区域</th><th>状态</th><th>默认仓</th><th>同步频率</th><th>最近自检</th><th>授权到期</th><th>操作</th></tr>
            </thead>
            <tbody>
                @forelse ($instances as $i)
                <tr>
                    <td>{{ $i->name }}</td>
                    <td class="font-monospace small">{{ $i->platform_key }}</td>
                    <td>{{ $i->region }}</td>
                    <td>
                        <span class="badge bg-{{ $i->last_check_result === 'failed' ? 'danger' : ($i->status ? 'success' : 'secondary') }}">{{ $i->statusLabel() }}</span>
                    </td>
                    <td>{{ $i->warehouse->warehouse_name ?? '-' }}</td>
                    <td>{{ $i->sync_frequency }}</td>
                    <td class="small">
                        {{ $i->last_check_at?->format('m-d H:i') ?? '-' }}
                        <div class="text-muted">{{ \Illuminate\Support\Str::limit($i->last_check_message, 60) }}</div>
                    </td>
                    <td class="{{ $i->isExpiringSoon() ? 'text-danger' : '' }}">{{ $i->expires_at?->toDateString() ?? '-' }}</td>
                    <td>
                        @can('ecom.operate')
                        <form method="POST" action="{{ route('ecom.instances.toggle', $i->id) }}">
                            @csrf
                            <input type="hidden" name="status" value="{{ $i->status ? 0 : 1 }}">
                            <button class="btn btn-sm btn-outline-{{ $i->status ? 'secondary' : 'success' }}">{{ $i->status ? '停用' : '启用' }}</button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">还没有接入任何平台，去接入向导选一个</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-muted">
        停用即不产生任何同步任务；订单拉取/发货回写本期全部为"待接入"，接入配置在后续交易闭环立项时直接复用。
    </div>
</div>
@endsection
