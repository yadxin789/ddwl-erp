@extends('layouts.admin')

@section('title', '回收站')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
    <div>
        <h4 class="mb-1"><i class="fa-solid fa-trash-can me-2"></i>回收站</h4>
        <div class="text-muted small">
            删除内容先进入回收站，物理文件保留 {{ $retentionDays }} 天；到期由系统自动彻底删除并释放磁盘空间。
        </div>
    </div>
    @if ($isAdmin)
        <div class="btn-group" role="group">
            <a class="btn btn-sm {{ $scope === 'mine' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('trash.index', ['type' => $type, 'scope' => 'mine']) }}">我的回收站</a>
            <a class="btn btn-sm {{ $scope === 'all' ? 'btn-primary' : 'btn-outline-secondary' }}"
               href="{{ route('trash.index', ['type' => $type, 'scope' => 'all']) }}">全系统回收站</a>
        </div>
    @endif
</div>

@if (session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

<div class="row g-2 mb-3">
    @foreach (\App\Services\TrashService::TYPES as $key => $config)
        <div class="col-md-4">
            <a class="card card-body py-2 text-decoration-none {{ $type === $key ? 'border-primary bg-primary-subtle' : '' }}"
               href="{{ route('trash.index', ['type' => $key, 'scope' => $scope]) }}">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="fw-semibold text-dark">
                        <i class="fa-solid {{ $config['icon'] }} me-2"></i>{{ $config['label'] }}
                    </span>
                    <span class="badge {{ $counts[$key] > 0 ? 'bg-danger' : 'bg-secondary' }}">{{ $counts[$key] }}</span>
                </div>
                <div class="small text-muted mt-1">点击查看该分类</div>
            </a>
        </div>
    @endforeach
</div>

<div class="card">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <span>
            <i class="fa-solid {{ \App\Services\TrashService::icon($type) }} me-1"></i>
            {{ \App\Services\TrashService::label($type) }}
            <span class="text-muted small ms-2">共 {{ $items->total() }} 条</span>
        </span>
        <div class="d-flex gap-2">
            <button type="submit" form="trashBatchForm" class="btn btn-sm btn-outline-success"
                    formaction="{{ route('trash.restore-batch', ['type' => $type, 'scope' => $scope]) }}">
                <i class="fa-solid fa-rotate-left me-1"></i>批量恢复
            </button>
            <button type="submit" form="trashBatchForm" class="btn btn-sm btn-outline-danger"
                    formaction="{{ route('trash.force-delete-batch', ['type' => $type, 'scope' => $scope]) }}"
                    onclick="return confirm('彻底删除后无法恢复，且会立即删除物理文件。确定继续吗？')">
                <i class="fa-solid fa-trash me-1"></i>批量彻底删除
            </button>
        </div>
    </div>

    <form id="trashBatchForm" method="POST"
          action="{{ route('trash.restore-batch', ['type' => $type, 'scope' => $scope]) }}">
        @csrf
    </form>

    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:36px">
                        <input type="checkbox" class="form-check-input" id="trashSelectAll" aria-label="全选">
                    </th>
                    <th>内容</th>
                    <th>归属人</th>
                    <th>删除人</th>
                    <th>删除时间</th>
                    <th class="text-end">大小</th>
                    <th>剩余保留</th>
                    <th class="text-end">操作</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($items as $item)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input trash-check"
                                   form="trashBatchForm" name="ids[]" value="{{ $item->id }}"
                                   aria-label="选择该条">
                        </td>
                        <td>
                            <div class="fw-semibold text-break">
                                {{ \App\Services\TrashService::displayName($type, $item) }}
                            </div>
                            <div class="small text-muted">#{{ $item->id }}</div>
                        </td>
                        <td class="small">{{ \App\Services\TrashService::ownerName($type, $item) }}</td>
                        <td class="small">{{ $item->deletedBy?->name ?: '系统 / 历史数据' }}</td>
                        <td class="small text-muted">{{ $item->deleted_at?->format('Y-m-d H:i') }}</td>
                        <td class="text-end small">{{ \App\Services\TrashService::humanSize(\App\Services\TrashService::size($type, $item)) }}</td>
                        <td class="small">
                            @php($days = \App\Services\TrashService::remainingDays($item, $retentionDays))
                            @if ($days > 0)
                                <span class="text-warning-emphasis">{{ $days }} 天</span>
                            @else
                                <span class="text-danger">即将清理</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <form method="POST" class="d-inline"
                                  action="{{ route('trash.restore', ['type' => $type, 'id' => $item->id, 'scope' => $scope]) }}">
                                @csrf
                                <button class="btn btn-sm btn-outline-success">恢复</button>
                            </form>
                            <form method="POST" class="d-inline"
                                  action="{{ route('trash.force-delete', ['type' => $type, 'id' => $item->id, 'scope' => $scope]) }}"
                                  onsubmit="return confirm('彻底删除后无法恢复，且会立即删除物理文件。确定继续吗？')">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">彻底删除</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="fa-solid fa-box-open d-block mb-2 fs-4"></i>
                            {{ $scope === 'all' ? '全系统回收站为空' : '你的回收站为空' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
document.getElementById('trashSelectAll')?.addEventListener('change', function () {
    document.querySelectorAll('.trash-check').forEach(function (checkbox) {
        checkbox.checked = this.checked;
    });
});
</script>
@endpush
