@extends('layouts.admin')

@section('title', 'BOM 导入预览')

@section('content')
@if ($errors->any())
    <div class="alert alert-danger py-2">
        @foreach ($errors->all() as $error) <div>{{ $error }}</div> @endforeach
    </div>
@endif

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            导入预览：{{ $import->import_no }}
            <span class="text-muted small ms-2">目标：{{ $import->product->material_code ?? '' }} {{ $import->product->name ?? '' }}</span>
        </span>
        <div>
            <span class="badge bg-success me-1">匹配成功 {{ $import->success_rows }}</span>
            <span class="badge bg-danger">需处理 {{ $import->error_rows }}</span>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>行号</th><th>MPN</th><th>物料名称</th><th>规格</th><th>制造商</th><th>用量</th><th>位号</th>
                        <th>匹配状态</th><th>内部料号</th><th>处理</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($import->rows as $row)
                    <tr class="{{ $row->match_status === 'matched' ? 'table-success' : ($row->match_status === 'multiple' ? 'table-warning' : 'table-danger') }}">
                        <td>{{ $row->row_no }}</td>
                        <td class="small">{{ $row->raw_mpn ?? '-' }}</td>
                        <td class="small">{{ $row->raw_name ?? '-' }}</td>
                        <td class="small">{{ $row->raw_spec ?? '-' }}</td>
                        <td class="small">{{ $row->raw_manufacturer ?? '-' }}</td>
                        <td>{{ $row->raw_qty ?? '-' }}</td>
                        <td class="small">{{ $row->raw_ref_des ?? '-' }}</td>
                        <td>
                            @if ($row->match_status === 'matched')
                                <span class="badge bg-success">精确匹配</span>
                            @elseif ($row->match_status === 'multiple')
                                <span class="badge bg-warning text-dark">多候选</span>
                            @elseif ($row->match_status === 'manual')
                                <span class="badge bg-info text-dark">人工处理</span>
                            @else
                                <span class="badge bg-danger">未匹配</span>
                            @endif
                        </td>
                        <td>
                            @if ($row->matchedMaterial)
                                <span class="font-monospace small">{{ $row->matchedMaterial->material_code }}</span>
                                <div class="small text-muted">{{ $row->matchedMaterial->name }}</div>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td>
                            @if ($row->match_status === 'unmatched')
                                <form method="POST" action="{{ route('bom.import.request-material', $row) }}" class="d-flex gap-1">
                                    @csrf
                                    <select name="category_code" class="form-select form-select-sm" style="width:90px">
                                        @foreach (['EL','ST','PB','PA','WK','SM','SP','OP','CH'] as $c)
                                            <option value="{{ $c }}">{{ $c }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-outline-danger">申请新料号</button>
                                </form>
                            @else
                                <span class="text-muted small">{{ $row->error_message ?? 'OK' }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 d-flex gap-2">
            <form method="POST" action="{{ route('bom.import.confirm', $import) }}">
                @csrf
                <button class="btn btn-primary" {{ $import->rows->where('action', 'new_material')->isNotEmpty() ? 'disabled' : '' }}>
                    <i class="fa-solid fa-check me-1"></i>确认导入（生成 draft BOM）
                </button>
            </form>
            <a href="{{ route('bom.import') }}" class="btn btn-outline-secondary">重新上传</a>
        </div>
        @if ($import->rows->where('action', 'new_material')->isNotEmpty())
            <div class="alert alert-warning mt-3 mb-0 py-2">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>
                存在 {{ $import->rows->where('action', 'new_material')->count() }} 行未匹配：请先为每行"申请新料号"，完成后才能确认导入（不允许跳过）。
            </div>
        @endif
    </div>
</div>
@endsection
