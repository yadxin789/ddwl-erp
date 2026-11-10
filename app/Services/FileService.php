<?php

namespace App\Services;

use App\Models\System\MediaFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * 文件上传服务：统一处理所有业务文件上传
 * - 存储位置：storage/app/uploads/{年}/{月}/
 * - 文件名：时间戳 + 随机串 + 原始扩展名（防重名/防路径注入）
 * - 落库 media_files（原始文件名/路径/大小/MIME/分类/上传人/关联业务）
 * - 最大 20MB；上传/下载不限制文件类型
 * - 在线预览只允许图片、Excel、PDF、Office 常用格式
 */
class FileService
{
    /**
     * 允许在线预览的扩展名。上传和下载不受此限制，避免设计文件包（zip/rar/7z/CAD 等）被拦截。
     */
    public const PREVIEWABLE_EXTENSIONS = [
        // 图片
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg',
        // Excel / CSV
        'xls', 'xlsx', 'csv',
        // PDF
        'pdf',
        // Office
        'doc', 'docx', 'ppt', 'pptx',
    ];

    /**
     * 上传文件
     *
     * @param UploadedFile $file 上传文件
     * @param string $category 分类：rnd_doc/chat_file/bom_excel/avatar/collab_doc
     * @param string|null $refType 关联业务类型
     * @param int|null $refId 关联业务ID
     * @return MediaFile 落库记录
     *
     * @throws \InvalidArgumentException 类型不允许/文件过大/上传失败
     */
    public function upload(UploadedFile $file, string $category, ?string $refType = null, ?int $refId = null): MediaFile
    {
        Log::info('文件上传', [
            'user_id' => Auth::id(),
            'category' => $category,
            'original_name' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
        // 1. 大小校验（20MB）
        if ($file->getSize() > 20 * 1024 * 1024) {
            throw new \InvalidArgumentException('文件不能超过 20MB');
        }

        // 2. 生成文件名。上传不限制类型，保留原扩展名；无扩展名时用 bin 兜底。
        $ext = strtolower($file->getClientOriginalExtension());
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        $mime = $file->getMimeType();

        // 3. 生成存储路径：uploads/{年}/{月}/时间戳_随机串.ext
        $folder = 'uploads/' . now()->format('Y') . '/' . now()->format('m');
        $filename = now()->format('YmdHis') . '_' . Str::random(8) . '.' . $ext;
        $path = $folder . '/' . $filename;

        // 4. 存储
        if (! Storage::disk('local')->putFileAs($folder, $file, $filename)) {
            throw new \RuntimeException('文件保存失败');
        }

        // 5. 落库
        return MediaFile::create([
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $mime,
            'category' => $category,
            'uploader_id' => Auth::id() ?? 0,
            'ref_type' => $refType,
            'ref_id' => $refId,
        ]);
    }

    /**
     * 下载/读取文件（返回绝对路径）
     */
    public function path(MediaFile $mediaFile): string
    {
        return Storage::disk('local')->path($mediaFile->file_path);
    }

    /**
     * 下载响应（P0-07）：只认 media_files 里记录的路径，拒绝任何穿越写法；
     * 新旧盘兼容：老资料文件存在 public 盘 resources/ 下，新文件统一 local 盘 uploads/ 下。
     * 授权由调用方的 MediaFilePolicy 完成。
     */
    public function downloadResponse(MediaFile $mediaFile)
    {
        $path = (string) $mediaFile->file_path;

        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/')) {
            abort(404);
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->download($path, $mediaFile->file_name);
            }
        }

        abort(404, '文件不存在或已归档');
    }

    /**
     * 是否允许在线预览：只允许图片、Excel/CSV、PDF、Office 常用办公格式。
     * 上传/下载不受该名单限制。
     */
    public function canPreview(MediaFile $mediaFile): bool
    {
        $ext = strtolower((string) pathinfo($mediaFile->file_name ?: $mediaFile->file_path, PATHINFO_EXTENSION));

        return in_array($ext, self::PREVIEWABLE_EXTENSIONS, true);
    }

    /**
     * 在线预览响应：仅对 canPreview() 放行的类型返回 inline 内容，其余 415。
     */
    public function previewResponse(MediaFile $mediaFile)
    {
        if (! $this->canPreview($mediaFile)) {
            abort(415, '该文件类型不支持在线预览，请下载后查看');
        }

        $path = (string) $mediaFile->file_path;
        if ($path === '' || str_contains($path, '..') || str_contains($path, '\\') || str_starts_with($path, '/')) {
            abort(404);
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, $mediaFile->file_name, [], 'inline');
            }
        }

        abort(404, '文件不存在或已归档');
    }

    /**
     * 删除文件（物理文件 + 记录）
     */
    public function delete(MediaFile $mediaFile): void
    {
        Log::info('文件删除', ['user_id' => Auth::id(), 'media_file_id' => $mediaFile->id, 'path' => $mediaFile->file_path]);
        Storage::disk('local')->delete($mediaFile->file_path);
        $mediaFile->delete();
    }
}
