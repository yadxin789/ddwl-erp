@extends('layouts.admin')

@section('title', '基本资料')

@section('content')
<div class="card" style="max-width:860px;">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fa-solid fa-id-card me-2 text-primary"></i>基本资料</span>
        <a href="{{ route('office.avatar') }}" class="btn btn-sm btn-outline-primary">
            <i class="fa-solid fa-image me-1"></i>上传头像
        </a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('office.profile.update') }}">
            @csrf
            <div class="row g-4">
                <div class="col-md-3 text-center border-end">
                    @if ($user->avatar_url)
                        <img src="{{ $user->avatar_url }}" alt="头像" class="rounded-circle border"
                             style="width:90px;height:90px;object-fit:cover;">
                    @else
                        <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center"
                             style="width:90px;height:90px;font-size:34px;">
                            {{ mb_substr($user->name, 0, 1) }}
                        </div>
                    @endif
                    <div class="mt-3 fw-semibold">{{ $user->identity_label }}</div>
                    <div class="text-muted small">{{ $user->position_label ?: '未绑定岗位' }}</div>
                    <a href="{{ route('office.avatar') }}" class="btn btn-sm btn-outline-secondary mt-3">
                        <i class="fa-solid fa-camera me-1"></i>更换头像
                    </a>
                </div>

                <div class="col-md-9">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">登录账号</label>
                            <input class="form-control" value="{{ $user->username }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">姓名 <span class="text-danger">*</span></label>
                            <input name="name" class="form-control" value="{{ old('name', $user->name) }}" required maxlength="64">
                            @error('name') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">所在部门</label>
                            <input class="form-control" value="{{ $user->department->dept_name ?? '未分配' }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">岗位</label>
                            <input class="form-control" value="{{ $user->position_label ?: '未绑定' }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">角色</label>
                            <input class="form-control" value="{{ $user->roles->pluck('name')->implode('、') ?: '未分配' }}" disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">邮箱</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" maxlength="128">
                            @error('email') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">个人简介</label>
                            <textarea name="bio" class="form-control" rows="3" maxlength="500"
                                      placeholder="可填写职责范围、专业方向或联系方式">{{ old('bio', $user->bio) }}</textarea>
                            @error('bio') <div class="text-danger small">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>保存</button>
                        <a href="{{ route('home') }}" class="btn btn-outline-secondary">返回工作台</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
