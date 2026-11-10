@extends('layouts.admin')

@section('title', '售后工单')

@section('content')
<div class="card">
    <div class="card-header">售后工单</div>
    <div class="card-body">
        <form method="POST" action="{{ route('marketing.tickets.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-md-2"><input name="customer_name" class="form-control form-control-sm" placeholder="客户姓名" required></div>
                <div class="col-md-2"><input name="customer_phone" class="form-control form-control-sm" placeholder="联系电话"></div>
                <div class="col-md-2">
                    <select name="type" class="form-select form-select-sm">
                        @foreach(\App\Enums\TicketType::cases() as $tt)<option value="{{ $tt->value }}">{{ $tt->label() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4"><input name="description" class="form-control form-control-sm" placeholder="问题描述" required></div>
                <div class="col-md-1"><button class="btn btn-sm btn-primary w-100">登记</button></div>
            </div>
        </form>
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>工单号</th><th>客户</th><th>类型</th><th>问题</th><th>状态</th></tr></thead>
        <tbody>
            @forelse ($tickets as $t)
            <tr><td class="font-monospace">{{ $t->ticket_no }}</td><td>{{ $t->customer_name }}</td><td><span class="badge bg-info">{{ \App\Enums\TicketType::tryFrom($t->type)?->label() ?? $t->type }}</span></td><td>{{ \Illuminate\Support\Str::limit($t->description, 40) }}</td><td><span class="badge bg-secondary text-dark">{{ \App\Enums\TicketStatus::tryFrom($t->status)?->label() ?? $t->status }}</span></td></tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-4">暂无售后工单</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $tickets->links() }}</div>
</div>
@endsection
