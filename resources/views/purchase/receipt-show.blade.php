@extends('layouts.admin')

@section('title', '收货单详情')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>收货单 {{ $receipt->receipt_no }}</span>
        <a href="{{ route('purchase.receipts') }}" class="btn btn-sm btn-outline-secondary">返回</a>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-3"><span class="text-muted">采购单：</span>{{ $receipt->purchaseOrder->po_no ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">供应商：</span>{{ $receipt->supplier->supplier_name ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">收货日期：</span>{{ $receipt->receipt_date }}</div>
            <div class="col-md-3"><span class="text-muted">仓库：</span>{{ $receipt->warehouse->name ?? '-' }}</div>
            <div class="col-md-3"><span class="text-muted">状态：</span>{{ $receipt->status }}</div>
        </div>

        @if ($receipt->status === 'pending_qc')
        @can('quality.receipt.inspect')
        <form method="POST" action="{{ route('purchase.receipts.inspect', $receipt) }}">
            @csrf
            <h6 class="fw-semibold mt-3">IQC 质检录入（合格转 available，不合格转 blocked）</h6>
            <table class="table table-sm table-bordered align-middle">
                <thead class="table-light"><tr><th>物料</th><th>批次</th><th>收货数量</th><th>合格数量</th><th>不合格数量</th></tr></thead>
                <tbody>
                    @foreach ($receipt->lines as $line)
                    <tr>
                        <td>{{ $line->material->material_code ?? '' }} {{ $line->material->name ?? '' }}
                            <input type="hidden" name="lines[{{ $loop->index }}][line_id]" value="{{ $line->id }}">
                        </td>
                        <td>{{ $line->lot_no }}</td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($line->qty,2),'0'),'.') }}</td>
                        <td><input type="number" name="lines[{{ $loop->index }}][qualified_qty]" step="0.01" min="0" max="{{ $line->qty }}" class="form-control form-control-sm" value="{{ $line->qty }}"></td>
                        <td><input type="number" name="lines[{{ $loop->index }}][rejected_qty]" step="0.01" min="0" max="{{ $line->qty }}" class="form-control form-control-sm" value="0"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <button class="btn btn-primary">提交质检结果</button>
        </form>
        @else
        <div class="alert alert-warning mt-3 mb-0">
            该收货单待 IQC 检验。判定由品质岗（IQC）执行，收货岗在此页只做登记与数量核对，不参与判定。
        </div>
        @endcan
        @else
        <h6 class="fw-semibold mt-3">质检结果</h6>
        <table class="table table-sm table-bordered align-middle">
            <thead class="table-light"><tr><th>物料</th><th>批次</th><th>收货数量</th><th>合格数量</th><th>不合格数量</th></tr></thead>
            <tbody>
                @foreach ($receipt->lines as $line)
                <tr>
                    <td>{{ $line->material->material_code ?? '' }} {{ $line->material->name ?? '' }}</td>
                    <td>{{ $line->lot_no }}</td>
                    <td class="text-end">{{ rtrim(rtrim(number_format($line->qty,2),'0'),'.') }}</td>
                    <td class="text-end text-success">{{ rtrim(rtrim(number_format($line->qualified_qty ?? 0,2),'0'),'.') }}</td>
                    <td class="text-end text-danger">{{ rtrim(rtrim(number_format($line->rejected_qty ?? 0,2),'0'),'.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endsection
