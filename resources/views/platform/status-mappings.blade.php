@extends('layouts.admin')

@section('title', '状态映射')

@section('content')
<div class="card">
    <div class="card-header">平台订单状态映射</div>
    <div class="card-body">
        <form method="POST" action="{{ route('platform.status-mappings.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2"><input name="platform" class="form-control form-control-sm" placeholder="平台如 taobao" required></div>
            <div class="col-md-3"><input name="platform_status" class="form-control form-control-sm" placeholder="平台状态如 WAIT_SELLER_SEND_GOODS" required></div>
            <div class="col-md-3"><input name="local_status" class="form-control form-control-sm" placeholder="本地状态如 shipped" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">添加</button></div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>平台</th><th>平台状态</th><th>本地状态</th></tr></thead>
        <tbody>
            @forelse ($mappings as $m)
            <tr><td>{{ $m->platform }}</td><td class="font-monospace">{{ $m->platform_status }}</td><td>{{ $m->local_status }}</td></tr>
            @empty
            <tr><td colspan="3" class="text-center text-muted py-4">暂无映射</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $mappings->links() }}</div>
</div>
@endsection
