@extends('layouts.admin')

@section('title', 'BOM管理')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>BOM列表（共 {{ $boms->total() }} 条）</span>
        @can('bom.import')
        <a href="{{ route('bom.import') }}" class="btn btn-primary btn-sm"><i class="fa-solid fa-file-import me-1"></i>Excel 导入</a>
        @endcan
    </div>
    <div class="card-body">
        <form class="row g-2 mb-3" method="GET">
            <div class="col-md-4">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="BOM编码/产品名称" value="{{ request('keyword') }}">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm">
                    <option value="">全部状态</option>
                    @foreach (\App\Enums\BomStatus::options() as $value => $label)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
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
                    <tr><th>BOM编码</th><th>产品</th><th>版本</th><th>状态</th><th>行数</th><th>创建人</th><th>操作</th></tr>
                </thead>
                <tbody>
                    @forelse ($boms as $bom)
                    <tr>
                        <td><span class="font-monospace">{{ $bom->bom_code }}</span></td>
                        <td>
                            {{ $bom->product->material_code ?? '-' }}
                            <div class="small text-muted">{{ $bom->product->name ?? '' }}</div>
                        </td>
                        <td>{{ $bom->version }}</td>
                        <td>
                            @php $label = \App\Enums\BomStatus::label($bom->status); @endphp
                            @if ($bom->status === 'approved') <span class="badge bg-success">{{ $label }}</span>
                            @elseif ($bom->status === 'draft') <span class="badge bg-secondary">{{ $label }}</span>
                            @elseif ($bom->status === 'pending') <span class="badge bg-warning text-dark">{{ $label }}</span>
                            @else <span class="badge bg-light text-muted">{{ $label }}</span> @endif
                        </td>
                        <td>{{ $bom->lines_count ?? '-' }}</td>
                        <td>{{ $bom->creator->name ?? '-' }}</td>
                        <td>
                            <a href="{{ route('bom.show', $bom) }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-eye"></i></a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">暂无BOM数据（P0-10 提供Excel导入）</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $boms->links() }}
    </div>
</div>
@endsection
