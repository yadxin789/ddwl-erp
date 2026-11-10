@extends('layouts.admin')

@section('title', '领域事件')

@section('content')
<div class="card">
    <div class="card-header">领域事件记录</div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>事件名</th><th>聚合</th><th>时间</th></tr></thead>
        <tbody>
            @forelse ($rows as $r)
            <tr>
                <td>{{ $r->name }}</td>
                <td class="small">{{ $r->agg_type }}#{{ $r->agg_id }}</td>
                <td class="small">{{ $r->created_at?->format('m-d H:i') }}</td>
            </tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-4">暂无领域事件</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $rows->links() }}</div>
</div>
@endsection
