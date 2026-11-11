@extends('layouts.admin')

@section('title', '工单详情')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>工单 {{ $order->order_no }}（{{ $order->product->material_code ?? '' }} {{ $order->product->name ?? '' }}）</span>
        <div class="d-flex gap-2">
            @if ($order->status === 'planned')
                @can('production.release')
                <form method="POST" action="{{ route('production.orders.release', $order) }}">@csrf <button class="btn btn-sm btn-info">下达工单</button></form>
                @endcan
            @endif
            <a href="{{ route('production.orders') }}" class="btn btn-sm btn-outline-secondary">返回</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><span class="text-muted">状态：</span>{{ \App\Support\DisplayText::status($order->status) }}</div>
            <div class="col-md-3"><span class="text-muted">计划/完工/不良：</span>{{ rtrim(rtrim(number_format($order->plan_qty,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($order->completed_qty,2),'0'),'.') }} / {{ rtrim(rtrim(number_format($order->defective_qty,2),'0'),'.') }}</div>
            <div class="col-md-3"><span class="text-muted">BOM：</span>{{ $order->bom->bom_code ?? '' }} v{{ $order->bom->version ?? '' }}</div>
        </div>

        {{-- BOM 物料需求 --}}
        <h6 class="fw-semibold">BOM 物料需求展开</h6>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>物料编码</th><th>名称</th><th>单位用量</th><th>需求数量</th></tr></thead>
            <tbody>
                @forelse ($requirements as $r)
                <tr><td class="font-monospace">{{ $r['material_code'] }}</td><td>{{ $r['material_name'] }}</td><td class="text-end">{{ $r['qty_per'] }}</td><td class="text-end">{{ $r['required_qty'] }}</td></tr>
                @empty
                <tr><td colspan="4" class="text-muted">无BOM明细</td></tr>
                @endforelse
            </tbody>
        </table>

        @if (in_array($order->status, ['released','in_progress']))
        <div class="row g-4 mt-2">
            {{-- 领料 --}}
            @can('production.issue')
            <div class="col-md-6">
                <h6 class="fw-semibold">领料出库</h6>
                <form method="POST" action="{{ route('production.orders.issue', $order) }}">
                    @csrf
                    <div class="mb-2">
                        <label class="form-label small">发料仓库</label>
                        <select name="warehouse_id" class="form-select form-select-sm" required>
                            @foreach ($warehouses as $w) <option value="{{ $w->id }}">{{ $w->name }}</option> @endforeach
                        </select>
                    </div>
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light"><tr><th>物料</th><th>批次</th><th>数量</th></tr></thead>
                        <tbody>
                            @foreach ($requirements as $r)
                            <tr>
                                <td><input type="hidden" name="material_id[]" value="{{ $r['material_id'] }}">{{ $r['material_code'] }}</td>
                                <td><input name="lot_no[]" class="form-control form-control-sm" placeholder="批次"></td>
                                <td><input name="qty[]" type="number" step="0.01" min="0" class="form-control form-control-sm" value="{{ $r['required_qty'] }}"></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button class="btn btn-sm btn-primary">确认领料</button>
                </form>
            </div>
            @endcan

            {{-- 报工 --}}
            @can('production.report')
            <div class="col-md-6">
                <h6 class="fw-semibold">工序报工</h6>
                <form method="POST" action="{{ route('production.orders.report', $order) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label small">工序</label>
                        <input name="operation_name" class="form-control form-control-sm" required placeholder="如 SMT/组装/测试">
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">良品数</label><input name="good_qty" type="number" step="0.01" min="0" class="form-control form-control-sm" required></div>
                        <div class="col-6"><label class="form-label small">不良数</label><input name="defective_qty" type="number" step="0.01" min="0" class="form-control form-control-sm" value="0"></div>
                        <div class="col-6"><label class="form-label small">不良代码</label><input name="defect_code" class="form-control form-control-sm"></div>
                        <div class="col-6"><label class="form-label small">工时</label><input name="work_hours" type="number" step="0.1" min="0" class="form-control form-control-sm"></div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-2">提交报工</button>
                </form>
                @endcan

                @can('production.complete')
                <h6 class="fw-semibold mt-4">完工入库</h6>
                <form method="POST" action="{{ route('production.orders.complete', $order) }}">
                    @csrf
                    <div class="mb-2"><label class="form-label small">入库仓库（成品/半成品仓）</label>
                        <select name="warehouse_id" class="form-select form-select-sm" required>
                            @foreach ($warehouses as $w) <option value="{{ $w->id }}">{{ $w->name }}</option> @endforeach
                        </select>
                    </div>
                    <button class="btn btn-sm btn-success" onclick="return confirm('按当前完工数量入库？')">完工入库</button>
                </form>
                @endcan
            </div>
        </div>
        @endif

        {{-- 报工记录 --}}
        <h6 class="fw-semibold mt-4">报工记录</h6>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>时间</th><th>工序</th><th>报工人</th><th>良品</th><th>不良</th><th>工时</th></tr></thead>
            <tbody>
                @forelse ($order->operationLogs as $log)
                <tr>
                    <td>{{ $log->reported_at }}</td><td>{{ $log->operation_name }}</td>
                    <td>{{ $log->worker->identity_label ?? '-' }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($log->good_qty,2),'0'),'.') }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($log->defective_qty,2),'0'),'.') }}</td>
                    <td class="text-end">{{ $log->work_hours }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-muted">暂无报工</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
