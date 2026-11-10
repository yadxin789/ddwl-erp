@extends('layouts.admin')

@section('title', 'AI OCR 识别')

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-header d-flex justify-content-between">
        <span>发票/单据 OCR 识别</span>
        <a href="{{ route('ai.chat') }}" class="btn btn-sm btn-outline-secondary">返回对话</a>
    </div>
    <div class="card-body text-center py-5">
        <i class="fa-solid fa-file-invoice fa-3x text-muted mb-3"></i>
        <p class="text-muted">上传发票图片/PDF，自动识别发票号、金额、日期、销方信息。</p>
        <p class="small text-warning">⚠ OCR 服务待接入腾讯云/阿里云 OCR API，当前为占位页面。</p>
        <label class="btn btn-primary">
            选择文件
            <input type="file" class="d-none" disabled>
        </label>
    </div>
</div>
@endsection
