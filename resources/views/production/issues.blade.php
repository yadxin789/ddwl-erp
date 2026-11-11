@extends('layouts.admin')

@section('title', '领料单')

@section('content')
<div class="card">
    <div class="card-header">生产领料单</div>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>领料单号</th><th>关联工单</th><th>仓库</th><th>状态</th><th>日期</th></tr></thead>
        <tbody>
            @forelse ($issues as $i)
            <tr>
                <td class="font-monospace">{{ $i->issue_no }}</td>
                <td>{{ $i->productionOrder->order_no ?? '-' }}</td>
                <td>{{ $i->warehouse->warehouse_code ?? '-' }}</td>
                <td><span class="badge bg-secondary text-dark">{{ \App\Support\DisplayText::status($i->status) }}</span></td>
                <td>{{ $i->issue_date }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无领料单（在工单详情中领料）</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $issues->links() }}</div>
</div>
@endsection
