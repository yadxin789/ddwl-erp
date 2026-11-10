@extends('layouts.admin')

@section('title', '仓库库位')

@section('content')
@foreach ($warehouses as $warehouse)
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <span class="font-monospace fw-bold">{{ $warehouse->warehouse_code }}</span>
            <span class="ms-2">{{ $warehouse->name }}</span>
            <span class="badge bg-secondary text-dark ms-2">{{ $warehouse->type }}</span>
            <span class="badge {{ $warehouse->status ? 'bg-success' : 'bg-secondary' }} ms-1">{{ $warehouse->status ? '启用' : '停用' }}</span>
        </div>
        <span class="text-muted small">库位 {{ $warehouse->locations->count() }} 个</span>
    </div>
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>库位编码</th><th>区域</th><th class="text-end">状态</th><th class="text-end">操作</th></tr></thead>
        <tbody>
            @forelse ($warehouse->locations as $location)
                <tr>
                    <td class="font-monospace">{{ $location->location_code }}</td>
                    <td>{{ $location->zone ?: '-' }}</td>
                    <td class="text-end">
                        <span class="badge {{ $location->status ? 'bg-success' : 'bg-secondary' }}">{{ $location->status ? '启用' : '停用' }}</span>
                    </td>
                    <td class="text-end">
                        @can('warehouse.edit')
                        <form method="POST" action="{{ route('warehouse.locations.toggle', $location) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-outline-secondary">{{ $location->status ? '停用' : '启用' }}</button>
                        </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted py-3">该仓库暂无库位</td></tr>
            @endforelse
        </tbody>
    </table>
    @can('warehouse.edit')
    <form method="POST" action="{{ route('warehouse.locations.store') }}" class="card-body d-flex gap-2 align-items-end">
        @csrf
        <input type="hidden" name="warehouse_id" value="{{ $warehouse->id }}">
        <div>
            <label class="form-label small mb-1">库位编码</label>
            <input type="text" name="location_code" class="form-control form-control-sm" required placeholder="A-01-03">
        </div>
        <div>
            <label class="form-label small mb-1">区域</label>
            <input type="text" name="zone" class="form-control form-control-sm" placeholder="合格区 / 待检区 / 冻结区">
        </div>
        <button class="btn btn-sm btn-outline-primary">新增库位</button>
    </form>
    @endcan
</div>
@endforeach
@endsection
