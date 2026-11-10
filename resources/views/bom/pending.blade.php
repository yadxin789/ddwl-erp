@extends('layouts.admin')

@section('title', 'BOM 待审批')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>BOM 待审批（草稿 {{ $boms->total() }} 条）</span>
        <a href="{{ route('bom.index') }}" class="btn btn-sm btn-outline-secondary">BOM 管理</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr><th>BOM 编号</th><th>产品</th><th>版本</th><th>行数</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
            </thead>
            <tbody>
                @forelse ($boms as $bom)
                <tr>
                    <td class="font-monospace">{{ $bom->bom_code }}</td>
                    <td>{{ $bom->product->material_code ?? '' }} {{ $bom->product->name ?? '' }}</td>
                    <td>{{ $bom->version }}</td>
                    <td>{{ $bom->lines_count }}</td>
                    <td><span class="badge bg-warning text-dark">草稿待审</span></td>
                    <td>{{ optional($bom->created_at)->format('Y-m-d H:i') }}</td>
                    <td class="d-flex gap-1">
                        <a href="{{ route('bom.show', $bom) }}" class="btn btn-sm btn-outline-primary">查看</a>
                        <form method="POST" action="{{ route('bom.approve', $bom) }}" class="d-inline" onsubmit="return confirm('确认审核通过该 BOM？')">
                            @csrf
                            <button class="btn btn-sm btn-success">审核通过</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">没有待审批的 BOM</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-body py-2">{{ $boms->links() }}</div>
</div>
@endsection
