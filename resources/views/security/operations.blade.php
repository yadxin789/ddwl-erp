@extends('layouts.admin')

@section('title', '操作日志')

@section('content')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <ul class="nav nav-tabs card-header-tabs mb-0">
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'login' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'login']) }}">登录日志</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'events' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'events']) }}">安全事件</a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ $tab === 'operations' ? 'active' : '' }}" href="{{ route('security.logs', ['tab' => 'operations']) }}">操作日志</a>
            </li>
        </ul>
    </div>
    <div class="card-body pb-2">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="operations">
            <div class="col-md-4">
                <label class="form-label small">关键字</label>
                <input type="text" name="q" class="form-control form-control-sm" maxlength="64"
                       value="{{ $q }}" placeholder="操作人 / 表名 / 单号 / 名称">
            </div>
            <div class="col-md-2">
                <label class="form-label small">动作</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">全部</option>
                    <option value="created" @selected($action === 'created')>新增</option>
                    <option value="updated" @selected($action === 'updated')>修改</option>
                    <option value="deleted" @selected($action === 'deleted')>删除</option>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-sm btn-outline-secondary w-100">查询</button>
            </div>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>#</th>
                    <th>时间</th>
                    <th>操作人</th>
                    <th>动作</th>
                    <th>对象</th>
                    <th>变更内容</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $log)
                @php
                    $before = $log->changes['before'] ?? [];
                    $after = $log->changes['after'] ?? [];
                    $fields = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
                    $fmt = function ($v) {
                        if (is_array($v)) {
                            return json_encode($v, JSON_UNESCAPED_UNICODE);
                        }
                        if (is_bool($v)) {
                            return $v ? 'true' : 'false';
                        }
                        if ($v === null) {
                            return 'null';
                        }
                        return (string) $v;
                    };
                @endphp
                <tr>
                    <td>{{ $log->id }}</td>
                    <td class="small text-nowrap">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>
                        <div>{{ $log->actor_name ?? $log->user?->identity_label ?? '系统' }}</div>
                        <div class="small text-muted">用户ID {{ $log->user_id ?? '-' }}</div>
                    </td>
                    <td>
                        @php
                            $badge = ['created' => 'success', 'updated' => 'primary', 'deleted' => 'danger'][$log->action] ?? 'secondary';
                        @endphp
                        <span class="badge bg-{{ $badge }}">{{ $log->action_label }}</span>
                    </td>
                    <td>
                        <div class="font-monospace small">{{ $log->target_type }}#{{ $log->target_id ?? '-' }}</div>
                        <div class="small">{{ $log->target_label ?? '-' }}</div>
                    </td>
                    <td>
                        @if (empty($fields))
                            <span class="text-muted small">无字段变更</span>
                        @else
                            <details>
                                <summary class="small text-muted">{{ count($fields) }} 个字段</summary>
                                <div class="small mt-1">
                                    @foreach ($fields as $field)
                                        <div class="mb-1">
                                            <span class="font-monospace text-muted">{{ $field }}</span>：
                                            @if (array_key_exists($field, $before))
                                                <span class="text-danger">{{ $fmt($before[$field]) }}</span>
                                            @endif
                                            @if (array_key_exists($field, $before) && array_key_exists($field, $after))
                                                <span class="text-muted">→</span>
                                            @endif
                                            @if (array_key_exists($field, $after))
                                                <span class="text-success">{{ $fmt($after[$field]) }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </details>
                        @endif
                    </td>
                    <td class="font-monospace small">{{ $log->ip_address ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">暂无操作日志</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">{{ $items->links() }}</div>
</div>
@endsection
