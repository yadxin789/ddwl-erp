<?php

namespace App\Models\Rnd;

use App\Models\Organization\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P5 项目组内协同文档：正文落库，断网可用；OnlyOffice 为可选增强。
 */
class RndCollabDocument extends Model
{
    protected $table = 'rnd_collab_documents';

    protected $fillable = ['project_id', 'title', 'content', 'version', 'creator_id', 'updater_id'];

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

    /** 纯文本摘要（列表展示用） */
    public function excerpt(int $len = 80): string
    {
        $text = trim(strip_tags((string) $this->content));

        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }
}
