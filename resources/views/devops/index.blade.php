@extends('layouts.admin')

@section('title', '部署运维')

@section('content')
<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">系统环境</div>
            <table class="table table-sm mb-0">
                <tbody>
                    @foreach ($info as $k => $v)
                    <tr><td class="text-muted" style="width:40%">{{ $k }}</td><td class="font-monospace">{{ $v }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">数据库状态</div>
            <div class="card-body">
                <p class="mb-2">连接：<strong>{{ $dbStatus }}</strong></p>
                <p class="mb-2">表数量：<strong>{{ $tables }}</strong></p>
                <p class="mb-3">已执行 migration：<strong>{{ $migrations }}</strong></p>
                <form method="POST" action="{{ route('devops.clear-cache') }}">
                    @csrf
                    <button class="btn btn-warning btn-sm">清理应用缓存</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
