@extends('layouts.admin')

@section('title', '修改密码')

@section('content')
<div class="card" style="max-width:560px;">
    <div class="card-header">修改密码</div>
    <div class="card-body">
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">当前密码</label>
                <input type="password" name="current_password" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">新密码</label>
                <input type="password" name="password" class="form-control" required minlength="6">
            </div>
            <div class="mb-3">
                <label class="form-label">确认新密码</label>
                <input type="password" name="password_confirmation" class="form-control" required>
            </div>
            <button class="btn btn-primary">更新密码</button>
            <a href="{{ route('office.profile') }}" class="btn btn-outline-secondary">返回</a>
        </form>
    </div>
</div>
@endsection
