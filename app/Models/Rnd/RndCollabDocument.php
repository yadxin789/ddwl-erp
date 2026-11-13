<?php

namespace App\Models\Rnd;

use App\Models\Concerns\RecordsDeletedBy;
use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

/**
 * P5 项目组内协同文档：真实 Office 文件落 file_repository，OnlyOffice 在线编辑。
 * 旧数据（仅有 content）在首次打开时自动转换为 Office 文件。
 */
class RndCollabDocument extends Model
{
    use SoftDeletes;
    use RecordsDeletedBy;

    protected $table = 'rnd_collab_documents';

    protected $fillable = [
        'project_id', 'title', 'doc_type', 'content', 'version',
        'extension', 'storage_path', 'doc_key', 'size',
        'creator_id', 'updater_id', 'deleted_by',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(RndProject::class, 'project_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updater_id');
    }

    public const TYPES = [
        'doc' => ['label' => 'Word 文档', 'icon' => 'bi-file-earmark-word', 'color' => 'text-primary', 'ext' => 'docx'],
        'sheet' => ['label' => 'Excel 表格', 'icon' => 'bi-file-earmark-spreadsheet', 'color' => 'text-success', 'ext' => 'xlsx'],
        'slide' => ['label' => 'PPT 演示', 'icon' => 'bi-file-earmark-slides', 'color' => 'text-danger', 'ext' => 'pptx'],
        'text' => ['label' => '纯文本', 'icon' => 'bi-file-earmark-text', 'color' => 'text-secondary', 'ext' => 'docx'],
    ];

    /** 扩展名（旧数据按 doc_type 推导） */
    public function fileExtension(): string
    {
        $ext = strtolower((string) $this->extension);
        if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
            return $ext;
        }

        return self::TYPES[$this->doc_type]['ext'] ?? 'docx';
    }

    public function fileName(): string
    {
        return $this->title . '.' . $this->fileExtension();
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->doc_type]['label'] ?? '在线文档';
    }

    public function typeIcon(): string
    {
        return self::TYPES[$this->doc_type]['icon'] ?? 'bi-file-earmark-text';
    }

    public function typeColor(): string
    {
        return self::TYPES[$this->doc_type]['color'] ?? 'text-secondary';
    }

    public function displaySize(): string
    {
        $bytes = (int) $this->size;
        if ($bytes <= 0) {
            $bytes = strlen((string) $this->content);
        }

        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 1) . ' MB'
            : ($bytes >= 1024 ? number_format($bytes / 1024, 1) . ' KB' : $bytes . ' B');
    }

    public function downloadName(): string
    {
        $ext = $this->hasRepositoryFile() ? $this->fileExtension() : 'txt';

        return str_replace(['/', '\\'], '_', $this->title) . '.' . $ext;
    }

    /** 纯文本摘要（列表展示用，Office 文件无正文时回退为空） */
    public function excerpt(int $len = 80): string
    {
        $text = trim(strip_tags((string) $this->content));

        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }

    /** 电话会议预览用文本：Office 文件不在服务端抽取正文，提示进入协同办公查看 */
    public function previewText(): string
    {
        $text = trim((string) $this->content);
        if ($text !== '') {
            return $text;
        }

        return 'Office 文档《' . $this->title . '》，请在协同办公中打开查看或编辑。';
    }

    public function hasRepositoryFile(): bool
    {
        return $this->storage_path
            && Storage::disk('file_repository')->exists($this->storage_path);
    }

    /** 生成/刷新 OnlyOffice 文档 key（内容变更后 key 必须变化） */
    public function refreshDocKey(): string
    {
        $this->doc_key = md5('rnd_collab_' . $this->id . '_' . time() . '_' . random_int(1000, 9999));

        return $this->doc_key;
    }
}
