<?php

namespace App\Services\Ecom;

use App\Models\Ecom\EcomOrder;
use App\Models\Ecom\EcomPlatform;
use App\Models\Ecom\PlatformInstance;
use App\Models\Event\IntegrationOutbox;
use App\Models\MasterData\Material;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** P3-08 电商订单拉取落库：幂等 upsert + SKU 映射 + 发货事件入 outbox。 */
class EcomOrderSyncService
{
    public function __construct(private AdapterRegistry $registry)
    {
    }

    public function sync(PlatformInstance $instance, array $options = []): array
    {
        if (! $instance->status) {
            return ['ok' => false, 'message' => '平台已停用，不产生同步任务。'];
        }
        if ($instance->expires_at && $instance->expires_at->isPast()) {
            return ['ok' => false, 'message' => '平台授权已过期，请先更新授权。'];
        }

        $adapter = $this->registry->make($instance->platform_key, [
            'api_base_url' => $instance->api_base_url,
            'field_mapping' => $instance->mapping_json['field_mapping'] ?? [],
            'status_mapping' => $instance->mapping_json['status_mapping'] ?? [],
        ]);
        if (! $adapter) {
            return ['ok' => false, 'message' => '未识别的平台适配器：' . $instance->platform_key];
        }

        try {
            $orders = $adapter->fetchOrders(
                (array) ($instance->credentials_json ?? []),
                array_merge($options, ['api_base_url' => $instance->api_base_url])
            );
        } catch (\Throwable $e) {
            $instance->update(['last_synced_at' => now()]);
            Log::warning('电商订单拉取失败', ['instance_id' => $instance->id, 'error' => $e->getMessage()]);

            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $ingested = $this->ingest($instance, $orders, $adapter);
        $instance->update(['last_synced_at' => now()]);

        return array_merge(['ok' => true, 'instance' => $instance->name], $ingested);
    }

    /**
     * 把统一结构的订单落库（不依赖 HTTP，测试/补单可复用）。
     * @param  array<int,array>  $orders
     */
    public function ingest(PlatformInstance $instance, array $orders, ?object $adapter = null): array
    {
        if ($adapter === null) {
            $adapter = $this->registry->make($instance->platform_key, [
                'api_base_url' => $instance->api_base_url,
                'field_mapping' => $instance->mapping_json['field_mapping'] ?? [],
                'status_mapping' => $instance->mapping_json['status_mapping'] ?? [],
            ]);
        }

        $platform = EcomPlatform::firstOrCreate(
            ['code' => mb_substr(str_replace('custom:', '', $instance->platform_key), 0, 32)],
            ['name' => $instance->name, 'status' => true]
        );

        $created = 0;
        $updated = 0;
        $queued = 0;

        DB::transaction(function () use ($instance, $platform, $orders, $adapter, &$created, &$updated, &$queued) {
            foreach ($orders as $payload) {
                $orderNo = trim((string) ($payload['order_no'] ?? ''));
                if ($orderNo === '') {
                    continue;
                }
                $status = (string) ($payload['status'] ?? 'pending');
                if (! in_array($status, ['pending', 'paid', 'shipped', 'completed', 'refund', 'closed'], true)) {
                    $status = 'pending';
                }

                $existing = EcomOrder::where('ecom_order_no', $orderNo)->first();
                if ($existing) {
                    $updated++;
                } else {
                    $created++;
                }

                $order = EcomOrder::updateOrCreate(
                    ['ecom_order_no' => $orderNo],
                    [
                        'platform_id' => $platform->id,
                        'platform_instance_id' => $instance->id,
                        'buyer_nick' => $payload['buyer_nick'] ?? null,
                        'receiver_name' => $payload['receiver_name'] ?? null,
                        'receiver_phone' => $payload['receiver_phone'] ?? null,
                        'receiver_address' => $payload['receiver_address'] ?? null,
                        'total_amount' => (float) ($payload['total_amount'] ?? 0),
                        'pay_amount' => (float) ($payload['pay_amount'] ?? 0),
                        'status' => $status,
                        'raw_payload' => $payload['raw'] ?? $payload,
                        'tracking_no' => ($payload['tracking_no'] ?? '') !== '' ? $payload['tracking_no'] : $existing?->tracking_no,
                        'paid_at' => $payload['paid_at'] ?? $existing?->paid_at,
                        'shipped_at' => $payload['shipped_at'] ?? ($status === 'shipped' ? now() : $existing?->shipped_at),
                        'pulled_at' => now(),
                        'last_error' => null,
                        'sync_status' => $status === 'shipped'
                            ? ($existing?->sync_status === 'pushed' ? 'pushed' : 'shipped')
                            : ($existing?->sync_status === 'pushed' ? 'pushed' : 'pulled'),
                    ]
                );

                $order->items()->delete();
                foreach ((array) ($payload['items'] ?? []) as $item) {
                    $sku = trim((string) ($item['outer_sku'] ?? ''));
                    $mapped = $sku !== ''
                        ? DB::table('platform_sku_mappings')
                            ->where('platform_instance_id', $instance->id)
                            ->where('platform_sku', $sku)
                            ->value('material_id')
                        : null;
                    $materialId = $mapped;
                    if (! $materialId && $sku !== '') {
                        $materialId = Material::where('material_code', $sku)->value('id');
                    }

                    $order->items()->create([
                        'outer_sku' => $sku !== '' ? $sku : null,
                        'product_name' => mb_substr((string) ($item['product_name'] ?? '未命名商品'), 0, 256),
                        'material_id' => $materialId,
                        'sku_mapped' => (bool) $materialId,
                        'qty' => max(1, (int) ($item['qty'] ?? 1)),
                        'price' => (float) ($item['price'] ?? 0),
                    ]);
                }

                if ($status === 'shipped') {
                    $queued += $this->enqueueShipment($order);
                }
            }
        });

        Log::info('电商订单已落库', [
            'instance_id' => $instance->id, 'created' => $created, 'updated' => $updated, 'queued' => $queued,
        ]);

        return ['created' => $created, 'updated' => $updated, 'queued' => $queued];
    }

    /** 发货回写事件入 outbox（同一订单只排一次待发任务）。 */
    public function enqueueShipment(EcomOrder $order): int
    {
        $exists = IntegrationOutbox::where('event_type', 'ecom.shipment.push')
            ->where('ref_type', 'ecom_order')
            ->where('ref_id', $order->id)
            ->whereIn('status', ['pending', 'failed'])
            ->exists();
        if ($exists) {
            return 0;
        }

        IntegrationOutbox::create([
            'event_type' => 'ecom.shipment.push',
            'ref_type' => 'ecom_order',
            'ref_id' => $order->id,
            'payload' => [
                'order_no' => $order->ecom_order_no,
                'tracking_no' => $order->tracking_no,
                'instance_id' => $order->platform_instance_id,
            ],
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        return 1;
    }
}
