@extends('layouts.admin')

@section('title', '接入 ' . $definition['name'])

@section('content')
<div class="card" style="max-width:820px">
    <div class="card-header d-flex justify-content-between">
        <span>② 凭证与参数 —— {{ $definition['name'] }}</span>
        <a href="{{ $definition['apply_url'] ?? '#' }}" target="_blank" rel="noopener" class="small">去开放平台申请</a>
    </div>
    <div class="card-body">
        <div class="alert alert-light border small">
            <strong>字段说明：</strong>{{ $definition['field_notes'] ?? '' }}<br>
            <span class="text-muted">本期只做接入：订单拉取、发货回写默认关闭（标"待接入"），不会产生同步任务。</span>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('ecom.onboarding.store', $platformKey) }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">实例名称（店铺名）</label>
                    <input name="instance_name" class="form-control" required value="{{ old('instance_name', $definition['name']) }}">
                </div>
                @foreach ($definition['required_credentials'] as $field => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    <input name="credentials[{{ $field }}]" class="form-control font-monospace" value="{{ old('credentials.' . $field) }}" required>
                </div>
                @endforeach
                <div class="col-md-4">
                    <label class="form-label">默认仓</label>
                    <select name="default_warehouse_id" class="form-select">
                        <option value="">暂不指定</option>
                        @foreach ($warehouses as $w)
                        <option value="{{ $w->id }}" @selected(old('default_warehouse_id') == $w->id)>{{ $w->warehouse_code }} {{ $w->warehouse_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">同步频率</label>
                    <select name="sync_frequency" class="form-select">
                        @foreach ($frequencies as $k => $v)<option value="{{ $k }}" @selected(old('sync_frequency', 'manual') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">授权到期日（提前 7 天提醒）</label>
                    <input type="date" name="expires_at" class="form-control" value="{{ old('expires_at') }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">回调地址（可选）</label>
                    <input name="callback_url" class="form-control" value="{{ old('callback_url') }}" placeholder="https://erp.example.com/api/platform/callback">
                </div>
                <div class="col-md-6">
                    <label class="form-label">字段映射 JSON（可选，留空用默认）</label>
                    <input name="mapping_json" class="form-control font-monospace" value="{{ old('mapping_json') }}" placeholder='{"order_no":"tid","sku":"outer_id"}'>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">③ 连接自检并保存</button>
                <a href="{{ route('ecom.onboarding') }}" class="btn btn-outline-secondary">上一步</a>
            </div>
        </form>
    </div>
</div>
@endsection
