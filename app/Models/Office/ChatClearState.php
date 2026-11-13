<?php

namespace App\Models\Office;

use Illuminate\Database\Eloquent\Model;

/**
 * 聊天室"清空我的留言板"游标：只影响当前用户自己的显示范围，
 * 不删除真实消息，与参考目录 ChatClearState 口径一致。
 */
class ChatClearState extends Model
{
    protected $table = 'chat_clear_states';

    protected $fillable = ['user_id', 'room_id', 'cleared_at'];

    protected $casts = ['cleared_at' => 'datetime'];
}
