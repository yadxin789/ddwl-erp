@extends('layouts.admin')

@section('title', '同步任务')

@section('content')
<div class="card">
    <div class="card-header">平台/ERP 同步任务</div>
    <div class="card-body">
        <form method="POST" action="{{ route('sync.tasks.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="任务名称" required></div>
            <div class="col-md-2">
                <select name="target" class="form-select form-select-sm">
                    <option>taobao</option><option>jd</option><option>pdd</option><option>douyin</option><option>wms</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm">
                    <option value="pull">拉取 pull</option><option value="push">推送 push</option>
                </select>
            </div>
            <div class="col-md-2"><input name="data_type" class="form-control form-control-sm" placeholder="order/product/inventory" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">创建</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>任务名</th><th>目标</th><th>方向</th><th>数据类型</th><th>状态</th><th>上次执行</th><th>操作</th></tr></thead>
        <tbody>
            @forelse ($tasks as $t)
            <tr>
                <td>{{ $t->name }}</td>
                <td><span class="badge bg-info">{{ $t->target }}</span></td>
                <td>{{ $t->direction }}</td>
                <td>{{ $t->data_type }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Enums\SyncTaskStatus::tryFrom($t->status)?->label() ?? $t->status }}</span></td>
                <td class="small">{{ $t->last_run_at?->format('Y-m-d H:i') ?? '-' }}</td>
                <td>
                    <form method="POST" action="{{ route('sync.tasks.toggle', $t) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-sm btn-outline-secondary">{{ $t->status === 'active' ? '暂停' : '启用' }}</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无同步任务</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $tasks->links() }}</div>
</div>
@endsection
