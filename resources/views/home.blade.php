@extends('layouts.admin')

@section('title', '工作台')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="fw-bold">{{ $dashboard['position_name'] ?? '未绑定岗位' }}</span>
        @if ($dashboard['department'])
            <span class="text-muted small ms-2">{{ $dashboard['department'] }}</span>
        @endif
    </div>
    <span class="text-muted small">只显示本岗指标</span>
</div>

@if ($dashboard['no_position'])
<div class="alert alert-warning">
    当前账号未绑定任何岗位角色，工作台不展示业务指标。请联系系统管理员在「岗位管理」中为你的账号绑定岗位。
</div>
@endif

<div class="row g-3">
    @forelse ($dashboard['cards'] as $card)
        <div class="col-sm-6 col-xl-3">
            <div class="card metric-card h-100"><div class="card-body d-flex align-items-center gap-3">
                <div class="metric-icon bg-{{ $card['color'] }} bg-opacity-10 text-{{ $card['color'] }}">
                    <i class="fa-solid {{ $card['icon'] }} fa-lg"></i>
                </div>
                <div>
                    <div class="text-muted small">{{ $card['label'] }}</div>
                    <div class="fs-5 fw-bold">
                        @if (! empty($card['money']))
                            ¥{{ number_format((float) $card['value'], 2) }}
                        @else
                            {{ is_numeric($card['value']) ? number_format((float) $card['value'], 0) : $card['value'] }}
                        @endif
                    </div>
                    @if ($card['url'])
                        <a href="{{ $card['url'] }}" class="small">进入 →</a>
                    @endif
                </div>
            </div></div>
        </div>
    @empty
        <div class="col-12"><div class="card"><div class="card-body text-muted">本岗暂未配置指标。</div></div></div>
    @endforelse
</div>

@include('partials.dashboard-charts', [
    'charts' => $dashboard['charts'],
    'cards' => $dashboard['cards'],
])

@if (! empty($dashboard['quick_links']))
<div class="card mt-3">
    <div class="card-header">本岗快捷入口</div>
    <div class="card-body d-flex flex-wrap gap-2">
        @foreach ($dashboard['quick_links'] as $link)
            <a href="{{ $link['url'] }}" class="btn btn-sm btn-outline-secondary">
                <i class="fa-solid {{ $link['icon'] }} me-1"></i>{{ $link['name'] }}
            </a>
        @endforeach
    </div>
</div>
@endif

<div class="card mt-3">
    <div class="card-body text-muted small">
        当前登录：<strong>{{ auth()->user()->identity_label }}</strong>
        @if(auth()->user()->position_label)
            · 岗位：{{ auth()->user()->position_label }}
        @endif
        · 角色：@foreach(auth()->user()->getRoleNames() as $r)<span class="badge bg-primary">{{ \App\Support\DisplayText::role($r) }}</span> @endforeach
    </div>
</div>
@endsection
