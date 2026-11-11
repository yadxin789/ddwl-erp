@extends('layouts.admin')

@section('title', '库存流水')

@section('content')
<div class="card">
    <div class="card-header">库存流水（共 {{ $transactions->total() }} 条）</div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <select name="material_id" class="form-select form-select-sm">
                    <option value="">全部物料</option>
                    @foreach ($materials as $material)
                        <option value="{{ $material->id }}" @selected(request('material_id') == $material->id)>
                            {{ $material->material_code }} {{ $material->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="txn_type" class="form-select form-select-sm">
                    <option value="">全部类型</option>
                    @foreach (\App\Enums\TransactionType::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('txn_type') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-outline-secondary btn-sm w-100">查询</button>
            </div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr><th>时间</th><th>物料</th><th>批号</th><th>类型</th><th class="text-end">变动数</th><th class="text-end">变动前</th><th class="text-end">变动后</th><th>状态变化</th><th>来源</th><th>操作人</th></tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $txn)
                    <tr>
                        <td class="small">{{ $txn->created_at }}</td>
                        <td>{{ $txn->material->material_code ?? '-' }} <span class="small text-muted">{{ $txn->material->name ?? '' }}</span></td>
                        <td><span class="font-monospace small">{{ $txn->lot_no }}</span></td>
                        <td><span class="badge bg-info text-dark">{{ $txn->txn_type }}</span></td>
                        <td class="text-end @if($txn->qty_change >= 0) text-success @else text-danger @endif fw-bold">{{ $txn->qty_change >= 0 ? '+' : '' }}{{ number_format($txn->qty_change) }}</td>
                        <td class="text-end">{{ number_format($txn->qty_before) }}</td>
                        <td class="text-end">{{ number_format($txn->qty_after) }}</td>
                        <td class="small">{{ $txn->from_status ?? '-' }} → {{ $txn->to_status ?? '-' }}</td>
                        <td class="small">{{ $txn->ref_type }}<span class="text-muted">#{{ $txn->ref_id ?? '-' }}</span></td>
                        <td>{{ $txn->operator->identity_label ?? '系统' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="10" class="text-center text-muted py-4">暂无流水数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $transactions->links() }}
    </div>
</div>
@endsection
