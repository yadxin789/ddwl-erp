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
 * - 最大 20MB，类型白名单（图片/Excel/PDF/Office）
 */
class FileService
{
    /**
     * 类型白名单：扩展名 => MIME 类型（允许多个）
     */
    private const ALLOWED_TYPES = [
        // 图片
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'bmp' => ['image/bmp'],
        'webp' => ['image/webp'],
        // Excel
        'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/octet-stream'],
        'csv' => ['text/csv', 'application/csv', 'text/plain', 'application/octet-stream'],
        // PDF
        'pdf' => ['application/pdf'],
        // Office
        'doc' => ['application/msword', 'application/octet-stream'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/octet-stream'],
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

        // 2. 扩展名 + MIME 白名单校验
        $ext = strtolower($file->getClientOriginalExtension());
        if (! array_key_exists($ext, self::ALLOWED_TYPES)) {
            throw new \InvalidArgumentException("不允许的文件类型: {$ext}");
        }

        $mime = $file->getMimeType();
        $allowedMimes = self::ALLOWED_TYPES[$ext];
        if (! in_array($mime, $allowedMimes, true)) {
            // 部分浏览器会识别为 application/octet-stream，兜底放行真实扩展名类型
            throw new \InvalidArgumentException("文件内容类型与扩展名不匹配: {$mime}");
        }

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
     * 删除文件（物理文件 + 记录）
     */
    public function delete(MediaFile $mediaFile): void
    {
        Log::info('文件删除', ['user_id' => Auth::id(), 'media_file_id' => $mediaFile->id, 'path' => $mediaFile->file_path]);
        Storage::disk('local')->delete($mediaFile->file_path);
        $mediaFile->delete();
    }
}
