<?php

namespace App\Http\Controllers;

use App\Models\Rnd\RndProject;
use App\Models\System\MediaFile;
use App\Models\System\WecomConfig;
use App\Services\FileService;
use Illuminate\Http\Request;

/**
 * 协同自助页（G6 / G13 / G14 / G12）：资料文件、企业微信绑定、我的项目列表
 * 均为"人人在用"的共享入口，权限以 auth 或 file.resource.* 区分。
 */
class WorkspaceController extends Controller
{
    public function __construct(protected FileService $files)
    {
    }
    /** P-133 我的项目列表：本人创建 + 本人担任任务执行人的项目 */
    public function myProjects(Request $request)
    {
        $userId = (int) $request->user()->id;

        // P0-05：与 RndController::projects 共用同一口径（RndProject::scopeMine）
        $projects = RndProject::mine($userId)
            ->with('creator')
            ->orderByDesc('id')
            ->paginate(12);

        return view('workspace.my-projects', compact('projects'));
    }

    /** P-130 资料文件：全员可下载，上传需 file.resource.manage */
    public function files(Request $request)
    {
        $files = MediaFile::query()
            ->with('uploader')
            ->where('category', 'resource')
            ->when($request->q, fn ($q, $kw) => $q->where('file_name', 'like', "%{$kw}%"))
            ->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('workspace.files', compact('files'));
    }

    public function fileUpload(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480'],
        ], [], ['file' => '文件']);

        // P0-07 / B-03：统一走 FileService（local 盘 uploads/，白名单校验），不再 store 到 public 盘
        try {
            $media = $this->files->upload($request->file('file'), 'resource');
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('success', '文件已上传：' . $media->file_name);
    }

    /**
     * P0-07 / B-02：文件下载统一入口，按业务归属授权（resource/collab_doc 登录可见，研发图档只给参与者）
     */
    public function fileDownload(MediaFile $media)
    {
        $this->authorize('download', $media);

        return $this->files->downloadResponse($media);
    }

    /** P-132 企业微信绑定：展示绑定状态与引导步骤（真实 OAuth 由运维配置 corp_id/secret 后生效） */
    public function wecom(Request $request)
    {
        $config = WecomConfig::query()->latest('id')->first();

        return view('workspace.wecom', [
            'config' => $config,
            'bound' => (bool) ($config && $config->status),
            'user' => $request->user(),
        ]);
    }
}
