@extends('layouts.admin')

@section('title', '检验记录')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>检验记录</span>
        <a href="{{ route('quality.inspections.create') }}" class="btn btn-primary btn-sm">新建检验</a>
    </div>
    <div class="card-body">
        <form method="GET" class="d-flex gap-2">
            <select name="stage" class="form-select form-select-sm" style="max-width:160px">
                <option value="">全部阶段</option>
                @foreach(\App\Enums\InspectionStage::cases() as $s)
                <option value="{{ $s->value }}" @selected(request('stage')===$s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <button class="btn btn-sm btn-outline-secondary">筛选</button>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>记录号</th><th>阶段</th><th>物料</th><th>抽检</th><th>合格</th><th>不良</th><th>结果</th><th>检验员</th><th>关联单据</th></tr></thead>
        <tbody>
            @forelse ($records as $r)
            <tr>
                <td class="font-monospace">{{ $r->record_no }}</td>
                <td><span class="badge bg-info">{{ \App\Enums\InspectionStage::tryFrom($r->stage)?->label() ?? $r->stage }}</span></td>
                <td>{{ $r->material->material_code ?? '-' }} {{ $r->material->name ?? '' }}</td>
                <td>{{ $r->sample_qty }}</td>
                <td>{{ $r->qualified_qty }}</td>
                <td>{{ $r->defect_qty }}</td>
                <td>
                    @php $res = \App\Enums\InspectionResult::tryFrom($r->result); @endphp
                    <span class="badge bg-{{ $r->result=='accepted'?'success':($r->result=='rejected'?'danger':'secondary') }}">{{ $res?->label() ?? $r->result }}</span>
                </td>
                <td>{{ $r->inspector->name ?? '-' }}</td>
                <td class="small">
                    @if ($r->ref_type === 'goods_receipt' && $r->ref_id)
                        <a href="{{ route('purchase.receipts.show', $r->ref_id) }}" class="font-monospace">GR#{{ $r->ref_id }}</a>
                    @else
                        <span class="text-muted">-</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="9" class="text-center text-muted py-4">暂无检验记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $records->links() }}</div>
</div>
@endsection
