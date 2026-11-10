@extends('layouts.admin')

@section('title', '个人资料')

@section('content')
<div class="card" style="max-width:680px;">
    <div class="card-header">个人资料</div>
    <div class="card-body">
        <form method="POST" action="{{ route('office.profile.update') }}">
            @csrf
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label">姓名</label><input name="name" class="form-control" value="{{ $user->name }}" required></div>
                <div class="col-md-6"><label class="form-label">账号</label><input class="form-control" value="{{ $user->username }}" disabled></div>
                <div class="col-md-6"><label class="form-label">手机</label><input name="phone" class="form-control" value="{{ $user->phone ?? '' }}"></div>
                <div class="col-md-6"><label class="form-label">邮箱</label><input name="email" class="form-control" value="{{ $user->email ?? '' }}"></div>
                <div class="col-12"><label class="form-label">个性签名</label><input name="bio" class="form-control" value="{{ $user->bio ?? '' }}"></div>
            </div>
            <button class="btn btn-primary mt-3">保存</button>
        </form>
    </div>
</div>
@endsection
