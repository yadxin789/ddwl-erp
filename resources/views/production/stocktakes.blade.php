@extends('layouts.admin')

@section('title', '盘点单')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>盘点单（共 {{ $stocktakes->total() }} 条）</span>
        <form method="POST" action="{{ route('production.stocktakes.create') }}" class="d-flex gap-1">
            @csrf
            <select name="warehouse_id" class="form-select form-select-sm" required>
                @foreach (\App\Models\Inventory\Warehouse::where('status',1)->get() as $w)
                    <option value="{{ $w->id }}">{{ $w->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-primary">新建盘点</button>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>盘点单号</th><th>仓库</th><th>状态</th><th>计划日期</th><th>完成时间</th><th>操作</th></tr></thead>
            <tbody>
                @php $smap = ['draft'=>'草稿','counting'=>'盘点中','completed'=>'已完成','adjusted'=>'已调整']; @endphp
                @forelse ($stocktakes as $s)
                <tr>
                    <td class="font-monospace">{{ $s->stocktake_no }}</td>
                    <td>{{ $s->warehouse->name ?? '-' }}</td>
                    <td><span class="badge bg-secondary text-dark">{{ $smap[$s->status] ?? $s->status }}</span></td>
                    <td>{{ $s->plan_date }}</td>
                    <td>{{ $s->finish_date ?? '-' }}</td>
                    <td><a href="{{ route('production.stocktakes.show', $s) }}" class="btn btn-sm btn-outline-primary">录入/调整</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center text-muted py-4">暂无盘点单</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $stocktakes->links() }}</div>
</div>
@endsection
