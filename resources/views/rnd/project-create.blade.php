@extends('layouts.admin')

@section('title', '新建研发项目')

@section('content')
<div class="card" style="max-width:720px;">
    <div class="card-header">新建研发项目</div>
    <div class="card-body">
        <form method="POST" action="{{ route('rnd.projects.store') }}">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">项目名称 <span class="text-danger">*</span></label>
                    <input name="project_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">产品型号</label>
                    <input name="product_model" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">开始日期</label>
                    <input type="date" name="start_date" class="form-control" value="{{ date('Y-m-d') }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label">计划结束</label>
                    <input type="date" name="end_date" class="form-control">
                </div>
            </div>
            <div class="mt-3">
                <button class="btn btn-primary">创建项目</button>
                <a href="{{ route('rnd.projects') }}" class="btn btn-outline-secondary">返回</a>
            </div>
        </form>
    </div>
</div>
@endsection
