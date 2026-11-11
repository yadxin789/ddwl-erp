<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAttachment extends Model
{
    protected $fillable = [
        'message_id', 'conversation_id', 'file_name', 'file_path', 'mime_type', 'file_size',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'message_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isImage(): bool
    {
        if ($this->mime_type && str_starts_with($this->mime_type, 'image/')) {
            return true;
        }

        return (bool) preg_match('/\.(jpg|jpeg|png|gif|bmp|webp)$/i', (string) $this->file_name);
    }

    public function icon(): string
    {
        $ext = strtolower(pathinfo((string) $this->file_name, PATHINFO_EXTENSION));

        return match ($ext) {
            'pdf' => 'fa-file-pdf-o',
            'doc', 'docx' => 'fa-file-word-o',
            'xls', 'xlsx', 'xlsm', 'csv', 'ods' => 'fa-file-excel-o',
            'ppt', 'pptx' => 'fa-file-powerpoint-o',
            'zip' => 'fa-file-archive-o',
            'txt', 'md', 'log' => 'fa-file-text-o',
            default => 'fa-file-o',
        };
    }

    public function readableSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes <= 0) {
            return '';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return round($bytes / 1024) . ' KB';
        }

        return $bytes . ' B';
    }
}
