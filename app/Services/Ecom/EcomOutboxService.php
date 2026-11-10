<?php

namespace App\Services\Ecom;

use App\Models\Ecom\EcomOrder;
use App\Models\Event\IntegrationOutbox;
use Illuminate\Support\Facades\Log;

/** P3-08 发货回写 outbox：指数退避重试，超过 5 次进死信。 */
class EcomOutboxService
{
    public const MAX_RETRIES = 5;

    public function __construct(private AdapterRegistry $registry)
    {
    }

    public function dispatchPending(int $limit = 50): array
    {
        $rows = IntegrationOutbox::where('event_type', 'ecom.shipment.push')
            ->whereIn('status', ['pending', 'failed'])
            ->where(function ($query) {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $sent = 0;
        $failed = 0;
        $dead = 0;

        foreach ($rows as $row) {
            $order = EcomOrder::find($row->ref_id);
            if (! $order || ! $order->instance) {
                $this->markFailure($row, '订单或平台实例不存在，无法回写发货。');
                $dead++;
                continue;
            }

            $adapter = $this->registry->make($order->instance->platform_key, [
                'api_base_url' => $order->instance->api_base_url,
                'field_mapping' => $order->instance->mapping_json['field_mapping'] ?? [],
                'status_mapping' => $order->instance->mapping_json['status_mapping'] ?? [],
            ]);
            if (! $adapter) {
                $this->markFailure($row, '未识别的平台适配器。');
                $dead++;
                continue;
            }

            $result = $adapter->pushShipment(
                (array) ($order->instance->credentials_json ?? []),
                [
                    'order_no' => $order->ecom_order_no,
                    'tracking_no' => $order->tracking_no,
                    'shipped_at' => $order->shipped_at?->toDateTimeString(),
                ],
                ['api_base_url' => $order->instance->api_base_url]
            );

            if ($result['ok']) {
                $row->update(['status' => 'published', 'published_at' => now(), 'last_error' => null, 'next_retry_at' => null]);
                $order->update(['sync_status' => 'pushed', 'pushed_at' => now(), 'last_error' => null]);
                $sent++;
                continue;
            }

            $failed++;
            if ($this->markFailure($row, $result['message'])) {
                $dead++;
            }
            $order->update(['sync_status' => 'error', 'last_error' => mb_substr($result['message'], 0, 255)]);
        }

        return ['sent' => $sent, 'failed' => $failed, 'dead' => $dead, 'processed' => $rows->count()];
    }

    /** 把死信重置为待发（人工确认平台恢复后使用）。 */
    public function retryDead(): int
    {
        return IntegrationOutbox::where('event_type', 'ecom.shipment.push')
            ->where('status', 'dead')
            ->update([
                'status' => 'pending',
                'retry_count' => 0,
                'next_retry_at' => null,
                'last_error' => null,
                'dead_at' => null,
            ]);
    }

    /** @return bool 是否已进死信 */
    private function markFailure(IntegrationOutbox $row, string $message): bool
    {
        $retry = $row->retry_count + 1;
        $isDead = $retry >= self::MAX_RETRIES;

        $row->update([
            'status' => $isDead ? 'dead' : 'failed',
            'retry_count' => $retry,
            'last_error' => mb_substr($message, 0, 255),
            'next_retry_at' => $isDead ? null : now()->addMinutes(5 * (2 ** ($retry - 1))),
            'dead_at' => $isDead ? now() : null,
        ]);

        Log::warning('电商发货回写失败', ['outbox_id' => $row->id, 'retry' => $retry, 'dead' => $isDead, 'error' => $message]);

        return $isDead;
    }
}
