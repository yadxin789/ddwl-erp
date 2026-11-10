@extends('layouts.admin')

@section('title', '收付款')

@section('content')
<div class="card">
    <div class="card-header">收付款登记</div>
    <div class="card-body">
        @can('finance.payment.execute')
        <form method="POST" action="{{ route('finance.payments.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-md-2">
                <select name="direction" class="form-select form-select-sm" required>
                    <option value="in">收款</option><option value="out">付款</option>
                </select>
            </div>
            <div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="金额" required></div>
            <div class="col-md-2">
                <select name="method" class="form-select form-select-sm">
                    <option value="bank">银行转账</option><option value="cash">现金</option><option value="alipay">支付宝</option><option value="wechat">微信</option>
                </select>
            </div>
            <div class="col-md-2"><input type="date" name="paid_at" class="form-control form-control-sm" required></div>
            <div class="col-md-2"><input name="ref_no" class="form-control form-control-sm" placeholder="流水号"></div>
            <div class="col-md-3">
                <select name="invoice_id" class="form-select form-select-sm">
                    <option value="">核销客户发票（收款用）</option>
                    @foreach ($invoices as $i)
                        <option value="{{ $i->id }}">{{ $i->invoice_no }} 未收 ¥{{ number_format((float) $i->amount - (float) $i->paid_amount, 2) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="bill_id" class="form-select form-select-sm">
                    <option value="">核销供应商账单（付款用）</option>
                    @foreach ($bills as $b)
                        <option value="{{ $b->id }}">{{ $b->bill_no }} 未付 ¥{{ number_format((float) $b->amount - (float) $b->paid_amount, 2) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2"><input name="allocate_amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="核销金额（默认全额）"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">登记</button></div>
        </form>
        <div class="form-text mt-2">收款只能核销客户发票、付款只能核销供应商账单；核销金额在收付款"执行"后才回写发票/账单（审批中的不虚增应收应付）。</div>
        @else
        <div class="alert alert-light border small mb-0">当前岗位只能查看收付款记录；登记与执行权限属出纳岗（finance.payment.execute）。</div>
        @endcan
    </div>
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light"><tr><th>单号</th><th>方向</th><th>金额</th><th>方式</th><th>日期</th><th>状态</th><th>核销</th></tr></thead>
        <tbody>
            @forelse ($payments as $p)
            <tr>
                <td class="font-monospace">{{ $p->payment_no }}</td>
                <td>@if($p->direction=='in')<span class="badge bg-success">收</span>@else<span class="badge bg-danger">付</span>@endif</td>
                <td>¥{{ number_format($p->amount, 2) }}</td>
                <td>{{ $p->method }}</td>
                <td>{{ $p->paid_at }}</td>
                <td>
                    @php($statusLabel = ['pending' => '待审批', 'approved' => '已批待执行', 'executed' => '已执行'][$p->status] ?? $p->status)
                    <span class="badge bg-secondary text-dark">{{ $statusLabel }}</span>
                </td>
                <td class="small">
                    @forelse ($p->allocations as $a)
                        {{ $a->invoice?->invoice_no ?? $a->bill?->bill_no ?? '-' }} ¥{{ number_format($a->amount, 2) }}@if(!$loop->last)<br>@endif
                    @empty
                        <span class="text-muted">-</span>
                    @endforelse
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-4">暂无收付款记录</td></tr>
            @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $payments->links() }}</div>
</div>
@endsection
