<?php

namespace App\Http\Controllers;

use App\Models\Rnd\RndProjectDocument;
use Illuminate\Http\Request;

/**
 * 设计图档（G5）：包装图档 / 外观图档两个独立岗位页。
 * 只按"任务类型名称"过滤，避免设计岗看到别人的机械图档。
 */
class DesignFileController extends Controller
{
    public function packaging(Request $request)
    {
        return $this->render('包装', 'design.pkg-files', '包装图档', $request);
    }

    public function identity(Request $request)
    {
        return $this->render('外观', 'design.id-files', '外观图档', $request);
    }

    private function render(string $keyword, string $routeName, string $title, Request $request)
    {
        $documents = RndProjectDocument::query()
            ->with(['project', 'task.taskType', 'file', 'uploader'])
            ->whereHas('task.taskType', fn ($q) => $q->where('name', 'like', "%{$keyword}%"))
            ->when($request->q, fn ($q, $kw) => $q->whereHas('project', fn ($p) => $p->where('project_name', 'like', "%{$kw}%")))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('design.files', [
            'documents' => $documents,
            'title' => $title,
            'keyword' => $keyword,
            'routeName' => $routeName,
        ]);
    }
}
