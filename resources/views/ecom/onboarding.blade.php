@extends('layouts.admin')

@section('title', '电商平台接入向导')

@section('content')
<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>① 选择平台（国内 / 国外）</span>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input name="q" value="{{ $keyword }}" class="form-control form-control-sm" placeholder="搜索平台名称 / 开放平台">
                <button class="btn btn-sm btn-outline-secondary">搜索</button>
            </form>
            <a href="{{ route('ecom.onboarding.custom') }}" class="btn btn-sm btn-outline-primary">手动添加任意平台</a>
            <a href="{{ route('ecom.instances') }}" class="btn btn-sm btn-outline-success">平台设置</a>
        </div>
    </div>
    <div class="card-body">
        @foreach (['domestic' => '国内平台', 'overseas' => '国外平台'] as $region => $label)
            <h6 class="mt-2">{{ $label }}</h6>
            <div class="row g-2">
                @forelse ($groups[$region] ?? [] as $key => $p)
                <div class="col-md-4">
                    <div class="border rounded p-2 h-100">
                        <div class="d-flex justify-content-between">
                            <strong>{{ $p['name'] }}</strong>
                            <a href="{{ route('ecom.onboarding.credentials', $key) }}" class="btn btn-sm btn-primary">接入</a>
                        </div>
                        <div class="small text-muted mt-1">
                            需 {{ count($p['required_credentials'] ?? []) }} 项凭证：
                            {{ implode('、', array_values($p['required_credentials'] ?? [])) }}
                        </div>
                        <div class="small mt-1">
                            <a href="{{ $p['apply_url'] ?? '#' }}" target="_blank" rel="noopener">去申请 / 开放平台入口</a>
                        </div>
                    </div>
                </div>
                @empty
                <div class="col-12 small text-muted">没有匹配的平台</div>
                @endforelse
            </div>
        @endforeach
    </div>
</div>

<div class="card">
    <div class="card-header">已接入实例（最近 20 个）</div>
    <table class="table table-sm mb-0">
        <thead class="table-light"><tr><th>实例</th><th>平台</th><th>区域</th><th>状态</th><th>自检</th></tr></thead>
        <tbody>
            @forelse ($instances as $i)
            <tr>
                <td>{{ $i->name }}</td>
                <td class="font-monospace">{{ $i->platform_key }}</td>
                <td>{{ $i->region }}</td>
                <td><span class="badge bg-{{ $i->status ? 'success' : 'secondary' }}">{{ $i->statusLabel() }}</span></td>
                <td class="small text-muted">{{ $i->last_check_message }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted py-3">还没有接入任何平台</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
