@extends('layouts.admin')

@section('title', '票据管理')

@section('content')
@can('finance.note.manage')
<div class="card mb-3"><div class="card-header">登记票据</div>
    <form method="POST" action="{{ route('finance.notes.store') }}" class="card-body row g-2">
        @csrf
        <div class="col-md-2"><input type="text" name="note_no" class="form-control form-control-sm" required placeholder="票据号" maxlength="32"></div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="bank">银行承兑</option>
                <option value="commercial">商业承兑</option>
            </select>
        </div>
        <div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control form-control-sm" required placeholder="票面金额"></div>
        <div class="col-md-2"><input type="text" name="drawer" class="form-control form-control-sm" placeholder="出票人" maxlength="64"></div>
        <div class="col-md-2"><input type="date" name="due_date" class="form-control form-control-sm" required></div>
        <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">登记</button></div>
    </form>
</div>
@endcan

<div class="card"><div class="card-header">票据台账</div>
    <table class="table table-sm mb-0 align-middle">
        <thead class="table-light"><tr><th>票据号</th><th>类型</th><th>出票人</th><th>到期日</th><th class="text-end">金额</th><th class="text-end">状态</th></tr></thead>
        <tbody>
        @forelse ($notes as $note)
            <tr>
                <td class="font-monospace small">{{ $note->note_no }}</td>
                <td class="small">{{ $note->type === 'bank' ? '银行承兑' : '商业承兑' }}</td>
                <td class="small">{{ $note->drawer ?? '-' }}</td>
                <td class="small">{{ $note->due_date?->format('Y-m-d') }}</td>
                <td class="text-end">¥{{ number_format((float) $note->amount, 2) }}</td>
                <td class="text-end"><span class="badge bg-secondary">{{ $note->status }}</span></td>
            </tr>
        @empty
            <tr><td colspan="6" class="text-center text-muted py-4">暂无票据</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<div class="mt-3">{{ $notes->links() }}</div>
@endsection
