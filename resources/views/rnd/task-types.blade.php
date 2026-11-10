@extends('layouts.admin')

@section('title', '研发任务类型字典')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>研发交付物类型字典（共 {{ $types->total() }} 类）</span>
        <a href="{{ route('rnd.projects') }}" class="btn btn-sm btn-outline-secondary">返回项目</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('rnd.task-types.store') }}" class="row g-2 mb-3 align-items-end">
            @csrf
            <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="类型名称（如：可靠性测试报告）" required></div>
            <div class="col-md-5"><input name="description" class="form-control form-control-sm" placeholder="说明"></div>
            <div class="col-md-2 form-check ms-2 pt-2">
                <input type="checkbox" name="is_required" value="1" class="form-check-input" id="req">
                <label class="form-check-label small" for="req">必选</label>
            </div>
            <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover align-middle mb-0">
        <thead class="table-light"><tr><th>#</th><th>排序</th><th>类型名称</th><th>说明</th><th>必选</th></tr></thead>
        <tbody>
            @foreach ($types as $t)
            <tr>
                <td>{{ $t->id }}</td>
                <td>{{ $t->sort_order }}</td>
                <td>{{ $t->name }}</td>
                <td class="small text-muted">{{ $t->description }}</td>
                <td>@if($t->is_required)<span class="badge bg-danger">必选</span>@else<span class="badge bg-light text-dark">可选</span>@endif</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div class="card-footer">{{ $types->links() }}</div>
</div>
@endsection
