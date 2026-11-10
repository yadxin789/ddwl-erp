@extends('layouts.admin')

@section('title', '财务报表')

@section('content')
<p class="text-muted small">收入按开票确认（D-10）；应收应付为未结清差额，含税口径。</p>
<div class="alert alert-light border small py-2 mb-2">数据范围：{{ $scopeLabel }}</div>
<div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-header">收入侧</div><div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted">开票金额</span><span class="fw-bold">¥{{ number_format($invoiced, 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">已回款</span><span class="fw-bold text-success">¥{{ number_format($collected, 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">应收未收</span><span class="fw-bold text-danger">¥{{ number_format($ar, 2) }}</span></div>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-header">支出侧</div><div class="card-body">
        <div class="d-flex justify-content-between"><span class="text-muted">应付款（账单额）</span><span class="fw-bold">¥{{ number_format($billed, 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">已付款</span><span class="fw-bold text-success">¥{{ number_format($paid, 2) }}</span></div>
        <div class="d-flex justify-content-between mt-2"><span class="text-muted">应付未付</span><span class="fw-bold text-danger">¥{{ number_format($ap, 2) }}</span></div>
    </div></div></div>
</div>

<div class="card mt-3"><div class="card-body small text-muted">
    资金收支明细、现金日记账、银行对账、票据管理与收付款执行属于<strong>出纳</strong>岗位页；本页只做汇总，不提供任何支付动作。
</div></div>
@endsection
