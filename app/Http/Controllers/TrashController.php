<?php

namespace App\Http\Controllers;

use App\Services\TrashService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * 回收站：个人范围（我删除的 / 我创建的），管理员可切换全系统范围。
 * 恢复只撤销软删除；彻底删除才清理物理文件。
 */
class TrashController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = $user?->hasRole('role_sys_admin') ?? false;

        $scope = $request->string('scope')->toString() === 'all' && $isAdmin ? 'all' : 'mine';
        $type = $request->string('type')->toString();
        if (! array_key_exists($type, TrashService::TYPES)) {
            $type = array_key_first(TrashService::TYPES);
        }

        $counts = [];
        foreach (TrashService::TYPES as $key => $config) {
            $counts[$key] = TrashService::count($key, $scope === 'all' ? null : (int) $user->id);
        }

        $query = TrashService::query($type);
        if ($scope !== 'all') {
            TrashService::scopeForUser($query, $type, (int) $user->id);
        }

        $query->with($this->relationsFor($type));
        $items = $query->orderByDesc('deleted_at')->paginate(20)->withQueryString();

        $retentionDays = (int) config('trash.retention_days', 15);

        return view('trash.index', compact(
            'items', 'counts', 'type', 'scope', 'isAdmin', 'retentionDays'
        ));
    }

    public function restore(Request $request, string $type, int $id)
    {
        $item = $this->findItem($type, $id);
        if (! $item) {
            return back()->with('error', '回收站内容不存在或已被彻底删除。');
        }

        if (! $this->canAct($request, $type, $item)) {
            return back()->with('error', '没有权限恢复该内容。');
        }

        TrashService::restore($type, $item);

        return back()->with('success', '已恢复：' . TrashService::displayName($type, $item));
    }

    public function forceDelete(Request $request, string $type, int $id)
    {
        $item = $this->findItem($type, $id);
        if (! $item) {
            return back()->with('error', '回收站内容不存在或已被彻底删除。');
        }

        if (! $this->canAct($request, $type, $item)) {
            return back()->with('error', '没有权限彻底删除该内容。');
        }

        try {
            TrashService::forceDeleteDeep($type, $item);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', '已彻底删除并释放磁盘空间。');
    }

    public function restoreBatch(Request $request, string $type)
    {
        [$items, $denied] = $this->batchItems($request, $type);
        $done = 0;

        foreach ($items as $item) {
            TrashService::restore($type, $item);
            $done++;
        }

        $message = "已恢复 {$done} 条。";
        if ($denied > 0) {
            $message .= " {$denied} 条无权操作，已跳过。";
        }

        return back()->with('success', $message);
    }

    public function forceDeleteBatch(Request $request, string $type)
    {
        [$items, $denied] = $this->batchItems($request, $type);
        $done = 0;
        $failed = 0;

        foreach ($items as $item) {
            try {
                TrashService::forceDeleteDeep($type, $item);
                $done++;
            } catch (\Throwable $e) {
                $failed++;
                report($e);
            }
        }

        $message = "已彻底删除 {$done} 条，释放对应磁盘空间。";
        if ($failed > 0) {
            $message .= " {$failed} 条因仍被业务引用或文件处理失败，已跳过。";
        }
        if ($denied > 0) {
            $message .= " {$denied} 条无权操作，已跳过。";
        }

        return back()->with('success', $message);
    }

    /** @return array{0: array<int, Model>, 1: int} */
    private function batchItems(Request $request, string $type): array
    {
        $this->assertType($type);

        $ids = collect($request->input('ids', []))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [[], 0];
        }

        $config = TrashService::config($type);
        /** @var class-string<Model> $model */
        $model = $config['model'];
        $query = $model::onlyTrashed()->whereIn('id', $ids);

        $scopeAll = $this->scopeAll($request);
        if (! $scopeAll) {
            TrashService::scopeForUser($query, $type, (int) Auth::id());
        }

        $allowed = $query->get();
        $denied = $ids->count() - $allowed->count();

        return [$allowed, $denied];
    }

    private function findItem(string $type, int $id): ?Model
    {
        $this->assertType($type);

        $config = TrashService::config($type);

        /** @var class-string<Model> $model */
        $model = $config['model'];

        return $model::onlyTrashed()->whereKey($id)->first();
    }

    private function canAct(Request $request, string $type, Model $item): bool
    {
        if ($this->scopeAll($request)) {
            return Auth::user()?->hasRole('role_sys_admin') ?? false;
        }

        return TrashService::isVisibleToUser($item, $type, (int) Auth::id());
    }

    private function scopeAll(Request $request): bool
    {
        return $request->string('scope')->toString() === 'all'
            && (Auth::user()?->hasRole('role_sys_admin') ?? false);
    }

    private function assertType(string $type): void
    {
        abort_unless(array_key_exists($type, TrashService::TYPES), 404);
    }

    /** @return array<int, string> */
    private function relationsFor(string $type): array
    {
        return match ($type) {
            'chat_messages' => ['sender', 'deletedBy'],
            'collab_documents' => ['creator', 'deletedBy'],
            'media_files' => ['uploader', 'deletedBy'],
            default => ['deletedBy'],
        };
    }
}
