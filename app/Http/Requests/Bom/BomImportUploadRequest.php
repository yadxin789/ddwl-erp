<?php

namespace App\Http\Requests\Bom;

use Illuminate\Foundation\Http\FormRequest;

class BomImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'product_material_id' => ['required', 'exists:materials,id'],
            'mpn_column' => ['required', 'string', 'max:32'],
            'qty_column' => ['required', 'string', 'max:32'],
            'name_column' => ['nullable', 'string', 'max:32'],
            'spec_column' => ['nullable', 'string', 'max:32'],
            'refdes_column' => ['nullable', 'string', 'max:32'],
            'manufacturer_column' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => '请选择 BOM Excel 文件',
            'file.max' => '文件不能超过 20MB',
            'product_material_id.required' => '请选择目标成品料号',
            'mpn_column.required' => '请指定 MPN 列',
            'qty_column.required' => '请指定数量列',
        ];
    }
}
