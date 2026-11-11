@extends('layouts.admin')

@section('title', '检验模板')

@section('content')
<div class="card">
    <div class="card-header">检验模板（IQC/IPQC/FQC/OQC）</div>
    <div class="card-body">
        <form method="POST" action="{{ route('quality.templates.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="code" class="form-control form-control-sm" placeholder="模板编号 TPL-IQC-01" required></div>
            <div class="col-md-3"><input name="name" class="form-control form-control-sm" placeholder="模板名称" required></div>
            <div class="col-md-2">
                <select name="stage" class="form-select form-select-sm" required>
                    <option>IQC</option><option>IPQC</option><option>FQC</option><option>OQC</option>
                </select>
            </div>
            <div class="col-md-2"><input name="standard" class="form-control form-control-sm" placeholder="AQL 标准"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>编号</th><th>名称</th><th>阶段</th><th>AQL</th><th>启用</th></tr></thead>
        <tbody>
            @forelse ($templates as $t)
            <tr><td class="font-monospace">{{ $t->code }}</td><td>{{ $t->name }}</td><td><span class="badge bg-info">{{ \App\Support\DisplayText::stage($t->stage) }}</span></td><td>{{ $t->standard ?? '-' }}</td><td>{{ $t->status ? '✅' : '❌' }}</td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无检验模板</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $templates->links() }}</div>
</div>
@endsection
