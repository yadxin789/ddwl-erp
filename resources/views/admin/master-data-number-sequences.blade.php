@extends('layouts.admin')

@section('title', '单据编号规则')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="h5 mb-1">单据编号规则</h1>
        <div class="small text-muted">
            归口部门：系统（非业务）。规则被业务编号生成器按编码直接调用，因此只允许修改与停用，不提供删除；
            停用后对应单据将无法生成新编号。
        </div>
    </div>
    <a href="{{ route('admin.master-data') }}" class="btn btn-sm btn-outline-secondary">返回归口台账</a>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <span>编号规则（{{ $rules->count() }}）</span>
        <span class="small text-muted">日期格式留空表示“前缀+流水号”</span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
            <tr>
                <th style="width:170px;">规则编码</th>
                <th style="width:110px;">前缀</th>
                <th style="width:110px;">日期格式</th>
                <th style="width:90px;">流水位数</th>
                <th style="width:100px;">当前流水</th>
                <th>说明</th>
                <th style="width:80px;">状态</th>
                <th style="width:90px;">操作</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($rules as $rule)
                <tr class="{{ $rule->status ? '' : 'table-secondary' }}">
                    <td class="font-monospace small">{{ $rule->rule_code }}</td>
                    <td>
                        <input type="text" name="prefix" form="seqForm{{ $rule->id }}" value="{{ $rule->prefix }}"
                               class="form-control form-control-sm" maxlength="16">
                    </td>
                    <td>
                        <input type="text" name="date_format" form="seqForm{{ $rule->id }}" value="{{ $rule->date_format }}"
                               class="form-control form-control-sm" maxlength="16" placeholder="如 Ymd">
                    </td>
                    <td>
                        <input type="number" name="seq_length" form="seqForm{{ $rule->id }}" value="{{ $rule->seq_length }}"
                               class="form-control form-control-sm" min="1" max="12" required>
                    </td>
                    <td>
                        <input type="number" name="current_seq" form="seqForm{{ $rule->id }}" value="{{ $rule->current_seq }}"
                               class="form-control form-control-sm" min="0" required>
                    </td>
                    <td>
                        <input type="text" name="description" form="seqForm{{ $rule->id }}" value="{{ $rule->description }}"
                               class="form-control form-control-sm" maxlength="255">
                    </td>
                    <td>
                        <select name="status" form="seqForm{{ $rule->id }}" class="form-select form-select-sm">
                            <option value="1" @selected($rule->status)>启用</option>
                            <option value="0" @selected(! $rule->status)>停用</option>
                        </select>
                    </td>
                    <td>
                        <form id="seqForm{{ $rule->id }}" method="POST" action="{{ route('admin.master-data.number-sequences.update', $rule) }}">
                            @csrf
                            @method('PUT')
                            <button class="btn btn-sm btn-outline-secondary">保存</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
