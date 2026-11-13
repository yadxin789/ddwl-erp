<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * P5 项目组内协同文档：正文落库，断网可用；OnlyOffice 为可选增强。
 */
class RndCollabDocument extends Model
{
    use SoftDeletes;

    protected $table = 'rnd_collab_documents';

    protected $fillable = ['project_id', 'title', 'doc_type', 'content', 'version', 'creator_id', 'updater_id'];

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
        'text' => ['label' => '纯文本', 'icon' => 'bi-file-earmark-text', 'color' => 'text-secondary', 'ext' => 'txt'],
    ];

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
        $bytes = strlen((string) $this->content);

        return $bytes >= 1024 * 1024
            ? number_format($bytes / 1024 / 1024, 1) . ' MB'
            : ($bytes >= 1024 ? number_format($bytes / 1024, 1) . ' KB' : $bytes . ' B');
    }

    public function downloadName(): string
    {
        return str_replace(['/', '\\'], '_', $this->title) . '.txt';
    }

    /** 纯文本摘要（列表展示用） */
    public function excerpt(int $len = 80): string
    {
        $text = trim(strip_tags((string) $this->content));

        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }
}
