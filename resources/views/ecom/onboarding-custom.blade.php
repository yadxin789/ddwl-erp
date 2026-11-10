@extends('layouts.admin')

@section('title', '手动添加平台')

@section('content')
<div class="card" style="max-width:860px">
    <div class="card-header">手动添加任意平台（通用 REST 适配器）</div>
    <div class="card-body">
        <div class="alert alert-light border small">
            填接口地址、鉴权方式、字段映射即可接入；保存前会做一次连通性自检（失败给出可读原因，不会 500）。
            本期只做配置与自检，不拉订单。
        </div>

        @if ($errors->any())
            <div class="alert alert-danger py-2 small">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('ecom.onboarding.custom.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">平台名称</label>
                    <input name="name" class="form-control" required value="{{ old('name') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">平台标识（英文/数字/横线）</label>
                    <input name="platform_key" class="form-control font-monospace" required value="{{ old('platform_key') }}" placeholder="my-oms">
                </div>
                <div class="col-md-4">
                    <label class="form-label">区域</label>
                    <select name="region" class="form-select">
                        <option value="domestic">国内</option>
                        <option value="overseas">国外</option>
                        <option value="custom">其他</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">接口地址（Base URL）</label>
                    <input name="api_base_url" class="form-control font-monospace" required value="{{ old('api_base_url') }}" placeholder="https://api.example.com">
                </div>
                <div class="col-md-4">
                    <label class="form-label">鉴权方式</label>
                    <select name="auth_type" class="form-select">
                        @foreach ($authTypes as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">API Key / Token</label>
                    <input name="api_key" class="form-control font-monospace" value="{{ old('api_key') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">默认仓</label>
                    <select name="default_warehouse_id" class="form-select">
                        <option value="">暂不指定</option>
                        @foreach ($warehouses as $w)<option value="{{ $w->id }}">{{ $w->warehouse_code }} {{ $w->warehouse_name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">同步频率</label>
                    <select name="sync_frequency" class="form-select">
                        @foreach ($frequencies as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">字段映射 JSON</label>
                    <textarea name="field_mapping" class="form-control font-monospace" rows="3">{{ old('field_mapping') }}</textarea>
                </div>
                <div class="col-md-6">
                    <label class="form-label">状态映射 JSON</label>
                    <textarea name="status_mapping" class="form-control font-monospace" rows="3">{{ old('status_mapping') }}</textarea>
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">连通性自检并保存</button>
                <a href="{{ route('ecom.onboarding') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
