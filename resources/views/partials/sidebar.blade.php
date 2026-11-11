@php
    $nav = app(\App\Support\NavigationService::class)->forCurrentUser();
@endphp

<nav class="nav flex-column">
    @if($nav['no_position'])
        <div class="nav-section">未绑定岗位</div>
        <div class="px-3 py-2 small text-muted">
            当前账号没有匹配的岗位角色，菜单已关闭。<br>
            请联系管理员在「岗位管理」中绑定岗位。
        </div>
        <a class="nav-link" href="{{ route('office.profile') }}">
            <i class="fa-solid fa-user-gear"></i>个人资料
        </a>
    @else
        @if($nav['home'])
            <div class="nav-section">{{ $nav['home']['name'] }}</div>
            <a class="nav-link {{ request()->routeIs($nav['home']['route']) || request()->routeIs($nav['home']['route'] . '*') ? 'active' : '' }}"
               href="{{ $nav['home']['url'] }}">
                <i class="fa-solid {{ $nav['home']['icon'] }}"></i>{{ $nav['home']['name'] }}
            </a>
        @endif

        @if(count($nav['business']))
            <div class="nav-section">{{ $nav['department'] ?: '业务' }}</div>
            @foreach($nav['business'] as $item)
                <a class="nav-link {{ request()->routeIs($item['route']) || request()->routeIs($item['route'] . '*') ? 'active' : '' }}"
                   href="{{ $item['url'] }}">
                    <i class="fa-solid {{ $item['icon'] }}"></i>{{ $item['name'] }}
                    @if(($item['badge'] ?? null) !== null)
                        <span class="badge {{ $item['badge'] > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        @endif

        @if(count($nav['shared']))
            <div class="nav-section">协同</div>
            @foreach($nav['shared'] as $item)
                <a class="nav-link {{ request()->routeIs($item['route']) || request()->routeIs($item['route'] . '*') ? 'active' : '' }}"
                   href="{{ $item['url'] }}">
                    <i class="fa-solid {{ $item['icon'] }}"></i>{{ $item['name'] }}
                    @if(($item['badge'] ?? null) !== null)
                        <span class="badge {{ $item['badge'] > 0 ? 'bg-danger' : 'bg-secondary' }} ms-1">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        @endif
    @endif
</nav>
