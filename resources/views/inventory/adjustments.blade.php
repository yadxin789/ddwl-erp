@extends('layouts.admin')

@section('title', '库存调整')

@section('content')
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">新建调整（以调整后数量为准）</div>
            <form method="POST" action="{{ route('inventory.adjustments.store') }}" class="card-body">
                @csrf
                <div class="mb-3">
                    <label class="form-label">库存行（物料 / 批次 / 库位）</label>
                    <select name="stock_id" class="form-select" required>
                        <option value="">请选择</option>
                        @foreach ($stocks as $stock)
                            <option value="{{ $stock->id }}">
                                {{ $stock->material->material_code ?? '-' }} · {{ $stock->material->name ?? '-' }}
                                | 批号 {{ $stock->lot_no ?: '-' }} | {{ $stock->warehouse->warehouse_code ?? '-' }}/{{ $stock->location->location_code ?? '-' }}
                                | 现存 {{ number_format((float) $stock->qty, 2) }} | 预留 {{ number_format((float) $stock->reserved_qty, 2) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">调整后数量</label>
                    <input type="number" step="0.01" min="0" name="new_qty" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">调整原因</label>
                    <input type="text" name="remark" class="form-control" maxlength="200" required placeholder="如：盘盈 / 破损报废 / 录入更正">
                </div>
                <button class="btn btn-primary w-100">提交调整申请</button>
                <div class="form-text mt-2">
                    审批阈值：{{ number_format((float) $threshold, 2) }} 元（|差异数量| × 单位成本）。
                    低于阈值的调整提交后立即生效并留痕（标"自动通过"）；达到或超过阈值须仓库主管审批，审批前库存不变。
                    已预留数量不允许被调低。
                </div>
            </form>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>待审批（{{ $pending->total() }} 条）</span>
                @cannot('inventory.adjust.approve')
                    <span class="text-muted small">审批权在仓库主管</span>
                @endcannot
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>调整单号</th><th>物料</th><th class="text-end">差异</th><th class="text-end">金额</th>
                            <th>发起人</th><th>原因</th><th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($pending as $adj)
                        <tr>
                            <td class="small font-monospace">{{ $adj->adjustment_no }}</td>
                            <td class="small">
                                {{ $adj->material->material_code ?? '-' }}
                                <div class="text-muted">批号 {{ $adj->lot_no ?: '-' }}</div>
                            </td>
                            <td class="text-end {{ (float) $adj->delta_qty >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ sprintf('%+0.2f', (float) $adj->delta_qty) }}
                                <div class="small text-muted">{{ number_format((float) $adj->before_qty, 2) }} → {{ number_format((float) $adj->after_qty, 2) }}</div>
                            </td>
                            <td class="text-end">
                                {{ $adj->amount === null ? '无法计价（强制审批）' : number_format((float) $adj->amount, 2) }}
                                <div class="small text-muted">阈值 {{ number_format((float) $adj->threshold, 2) }}</div>
                            </td>
                            <td class="small">{{ $adj->requester->name ?? '-' }}</td>
                            <td class="small text-muted">{{ $adj->reason }}</td>
                            <td>
                                @can('inventory.adjust.approve')
                                <div class="d-flex gap-1">
                                    <form method="POST" action="{{ route('inventory.adjustments.approve', $adj->id) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">通过</button>
                                    </form>
                                    <form method="POST" action="{{ route('inventory.adjustments.reject', $adj->id) }}" class="d-flex gap-1">
                                        @csrf
                                        <input type="text" name="remark" class="form-control form-control-sm" style="max-width:110px" placeholder="驳回原因" required>
                                        <button class="btn btn-sm btn-outline-danger">驳回</button>
                                    </form>
                                </div>
                                @else
                                <span class="text-muted small">待主管审批</span>
                                @endcan
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">没有待审批的调整单</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2">{{ $pending->links() }}</div>
        </div>

        <div class="card">
            <div class="card-header">调整记录（含自动通过与已审批）</div>
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>时间</th><th>单号</th><th>物料</th><th class="text-end">变动</th><th class="text-end">金额</th><th>状态</th><th>原因</th></tr>
                </thead>
                <tbody>
                    @php
                        $smap = ['approved' => '已审批', 'auto_approved' => '自动通过', 'void' => '已驳回'];
                        $bmap = ['approved' => 'success', 'auto_approved' => 'info', 'void' => 'secondary'];
                    @endphp
                    @forelse ($history as $adj)
                        <tr>
                            <td class="small text-muted">{{ $adj->created_at?->format('m-d H:i') }}</td>
                            <td class="small font-monospace">{{ $adj->adjustment_no }}</td>
                            <td class="small">{{ $adj->material->material_code ?? '-' }}</td>
                            <td class="text-end {{ (float) $adj->delta_qty >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ sprintf('%+0.2f', (float) $adj->delta_qty) }}
                            </td>
                            <td class="text-end small">{{ $adj->amount === null ? '-' : number_format((float) $adj->amount, 2) }}</td>
                            <td>
                                <span class="badge bg-{{ $bmap[$adj->status] ?? 'secondary' }}">{{ $smap[$adj->status] ?? $adj->status }}</span>
                            </td>
                            <td class="small text-muted">{{ $adj->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">暂无调整记录</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="card-footer py-2">{{ $history->links() }}</div>
        </div>
    </div>
</div>
@endsection
