@extends('layouts.admin')

@section('title', '编辑用户')

@section('content')
<div class="card" style="max-width: 760px;">
    <div class="card-header">编辑用户：{{ $user->username }}</div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">登录账号 <span class="text-danger">*</span></label>
                    <input type="text" name="username" class="form-control" value="{{ $user->username }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">用户名（姓名） <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">邮箱</label>
                    <input type="email" name="email" class="form-control" value="{{ $user->email }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">重置密码（留空不修改）</label>
                    <input type="password" name="password" class="form-control" placeholder="至少6位">
                </div>
                <div class="col-md-6">
                    <label class="form-label">部门</label>
                    <select name="department_id" class="form-select" onchange="loadPositions(this)">
                        <option value="">无</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept->id }}" @selected($user->department_id === $dept->id)>{{ $dept->dept_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">岗位</label>
                    <select name="position_id" class="form-select" id="positionSelect">
                        <option value="">无</option>
                        @foreach ($positions as $pos)
                            <option value="{{ $pos->id }}" @selected($user->position_id === $pos->id)>{{ $pos->pos_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">租户（留空=平台账号，见全量数据）</label>
                    <select name="tenant_id" class="form-select">
                        <option value="">平台账号（不隔离）</option>
                        @foreach ($tenants as $tenant)
                            <option value="{{ $tenant->id }}" @selected((int) $user->tenant_id === $tenant->id)>{{ $tenant->code }} · {{ $tenant->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">状态</label>
                    <select name="status" class="form-select">
                        <option value="1" @selected($user->status === 1)>启用</option>
                        <option value="0" @selected($user->status === 0)>禁用</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">角色（可多选）</label>
                    <select name="roles[]" class="form-select" multiple size="5">
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}" @selected($user->hasRole($role))>{{ \App\Support\DisplayText::role($role->name) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4 d-flex gap-2">
                <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存</button>
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    const positionsByDept = @json(
        \App\Models\Organization\Position::all()->groupBy('department_id')->map(fn ($items) => $items->map(fn ($p) => ['id' => $p->id, 'name' => $p->pos_name]))
    );
    const currentPosition = {{ $user->position_id ?? 'null' }};
    function loadPositions(select) {
        const target = document.getElementById('positionSelect');
        target.innerHTML = '<option value="">无</option>';
        (positionsByDept[select.value] || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id; opt.textContent = p.name;
            if (p.id === currentPosition) opt.selected = true;
            target.appendChild(opt);
        });
    }
</script>
@endpush
