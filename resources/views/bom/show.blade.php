@extends('layouts.admin')

@section('title', 'BOM详情')

@section('content')
<div class="card">
    <div class="card-header">BOM：<span class="font-monospace">{{ $bom->bom_code }}</span> v{{ $bom->version }}</div>
    <div class="card-body">
        <dl class="row">
            <dt class="col-sm-2">产品</dt>
            <dd class="col-sm-4">{{ $bom->product->material_code ?? '-' }} {{ $bom->product->name ?? '' }}</dd>
            <dt class="col-sm-2">状态</dt>
            <dd class="col-sm-4">
                {{ \App\Enums\BomStatus::label($bom->status) }}
                @if ($bom->status === 'draft')
                    <form method="POST" action="{{ route('bom.approve', $bom) }}" class="d-inline ms-2">
                        @csrf
                        <button class="btn btn-sm btn-success"><i class="fa-solid fa-check me-1"></i>审核通过</button>
                    </form>
                @endif
            </dd>
        </dl>
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr><th>#</th><th>物料编码</th><th>物料名称</th><th>规格</th><th>用量</th><th>单位</th><th>位号</th><th>是否替换料</th></tr>
            </thead>
            <tbody>
                @forelse ($bom->lines as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><span class="font-monospace">{{ $line->material->material_code ?? $line->material_code }}</span></td>
                    <td>{{ $line->material->name ?? '-' }}</td>
                    <td class="small">{{ $line->material->spec ?? $line->spec }}</td>
                    <td>{{ $line->qty_per }}</td>
                    <td>{{ $line->unit ?? '-' }}</td>
                    <td>{{ $line->ref_des ?? '-' }}</td>
                    <td>{{ $line->is_alternative ? '是' : '否' }}</td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center text-muted py-4">暂无BOM行</td></tr>
                @endforelse
            </tbody>
        </table>
        <a href="{{ route('bom.index') }}" class="btn btn-outline-secondary btn-sm">返回</a>
    </div>
</div>
@endsection
