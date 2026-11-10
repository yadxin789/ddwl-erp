@extends('layouts.admin')

@section('title', '审批中心')

@section('content')
<div class="card">
    <div class="card-header">待审批事项</div>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>业务类型</th><th>业务单据</th><th>当前步骤</th><th>状态</th><th>操作</th></tr></thead>
        <tbody>
            @forelse ($instances as $ins)
            <tr>
                <td>{{ $ins->business_type }}</td>
                <td class="font-monospace">#{{ $ins->business_id }}</td>
                <td>第 {{ $ins->current_step }} 级</td>
                <td><span class="badge bg-warning text-dark">{{ $ins->status }}</span></td>
                <td>
                    <form method="POST" action="{{ route('approvals.decide', $ins) }}" class="d-flex gap-1">
                        @csrf
                        <input type="hidden" name="pass" value="1">
                        <button class="btn btn-sm btn-success">同意</button>
                    </form>
                    <form method="POST" action="{{ route('approvals.decide', $ins) }}" class="d-flex gap-1">
                        @csrf
                        <input type="hidden" name="pass" value="0">
                        <button class="btn btn-sm btn-danger">驳回</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无待审批事项</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $instances->links() }}</div>
</div>
@endsection
