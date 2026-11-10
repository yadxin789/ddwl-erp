<?php

namespace App\Services;

use App\Models\Office\SystemNotification;
use App\Models\Office\TodoItem;
use Illuminate\Support\Facades\Auth;

/**
 * 消息通知与待办服务：所有业务事件统一调用
 */
class NotificationService
{
    public function send(int $userId, string $type, string $title, ?string $content = null, ?string $businessType = null, ?int $businessId = null): SystemNotification
    {
        return SystemNotification::create([
            'user_id' => $userId,
            'notify_type' => $type,
            'title' => $title,
            'content' => $content,
            'is_read' => false,
            'business_type' => $businessType,
            'business_id' => $businessId,
            'created_at' => now(),
        ]);
    }

    public function addTodo(int $userId, string $type, string $title, $deadline = null, ?string $businessType = null, ?int $businessId = null): TodoItem
    {
        return TodoItem::create([
            'user_id' => $userId,
            'todo_type' => $type,
            'title' => $title,
            'deadline' => $deadline,
            'is_finished' => false,
            'business_type' => $businessType,
            'business_id' => $businessId,
            'created_at' => now(),
        ]);
    }

    public function unreadCount(int $userId): int
    {
        return SystemNotification::where('user_id', $userId)->where('is_read', false)->count();
    }
}
