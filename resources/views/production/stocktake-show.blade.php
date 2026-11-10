@extends('layouts.admin')

@section('title', '盘点明细')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>盘点单 {{ $stocktake->stocktake_no }} — {{ $stocktake->warehouse->name ?? '' }}</span>
        <a href="{{ route('production.stocktakes') }}" class="btn btn-sm btn-outline-secondary">返回</a>
    </div>
    <div class="card-body">
        @if ($stocktake->status === 'adjusted')
            <div class="alert alert-success">本盘点单已完成差异调整。</div>
        @else
            <form method="POST" action="{{ route('production.stocktakes.adjust', $stocktake) }}">
                @csrf
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light"><tr><th>物料</th><th>批次</th><th>账面数量</th><th>实盘数量</th><th>差异</th></tr></thead>
                    <tbody>
                        @foreach ($stocktake->lines as $line)
                        <tr>
                            <td>{{ $line->material->material_code ?? '' }} {{ $line->material->name ?? '' }}</td>
                            <td>{{ $line->lot_no }}</td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($line->book_qty,2),'0'),'.') }}</td>
                            <td><input name="actual[{{ $line->id }}]" type="number" step="0.01" class="form-control form-control-sm" value="{{ $line->book_qty }}"></td>
                            <td class="text-end">
                                @php $diff = ($line->actual_qty ?? 0) - $line->book_qty; @endphp
                                {{ $line->actual_qty !== null ? rtrim(rtrim(number_format($line->diff_qty,2),'0'),'.') : '-' }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <button class="btn btn-primary">提交实盘并调整差异</button>
            </form>
        @endif
    </div>
</div>
@endsection
