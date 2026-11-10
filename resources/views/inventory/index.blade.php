@extends('layouts.admin')

@section('title', '库存查询')

@section('content')
<div class="card">
    <div class="card-header">库存查询（共 {{ $stocks->total() }} 条）</div>
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
                <select name="warehouse_id" class="form-select form-select-sm">
                    <option value="">全部仓库</option>
                    @foreach ($warehouses as $warehouse)
                        <option value="{{ $warehouse->id }}" @selected(request('warehouse_id') == $warehouse->id)>
                            {{ $warehouse->warehouse_code }} {{ $warehouse->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">全部状态</option>
                    @foreach (\App\Enums\StockStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === (string) $value)>{{ $label }}</option>
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
                    <tr><th>物料</th><th>批号</th><th>仓库</th><th>库位</th><th>状态</th><th class="text-end">库存数量</th><th class="text-end">预留数量</th><th class="text-end">可用数量</th></tr>
                </thead>
                <tbody>
                    @forelse ($stocks as $stock)
                    <tr>
                        <td>
                            {{ $stock->material->material_code ?? '-' }}
                            <div class="small text-muted">{{ $stock->material->name ?? '' }}</div>
                        </td>
                        <td><span class="font-monospace small">{{ $stock->lot_no }}</span></td>
                        <td>{{ $stock->warehouse->warehouse_code ?? '-' }}</td>
                        <td>{{ $stock->location->location_code ?? '-' }}</td>
                        <td>
                            @php $label = \App\Enums\StockStatus::from($stock->status)->label(); @endphp
                            @if ($stock->status === 'available')
                                <span class="badge bg-success">{{ $label }}</span>
                            @elseif ($stock->status === 'frozen' || $stock->status === 'quarantine')
                                <span class="badge bg-warning text-dark">{{ $label }}</span>
                            @else
                                <span class="badge bg-secondary">{{ $label }}</span>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format($stock->qty) }}</td>
                        <td class="text-end">{{ number_format($stock->reserved_qty) }}</td>
                        <td class="text-end fw-bold">{{ number_format($stock->qty - $stock->reserved_qty) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">暂无库存数据</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $stocks->links() }}
    </div>
</div>
@endsection
