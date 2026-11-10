<?php

namespace App\Services\Ecom\Adapters;

use App\Services\Ecom\Contracts\PlatformAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * P1-07 通用适配器：
 * - 预置平台：按 config/platforms.php 的 required_credentials 做完整性校验（不发起真实 API 调用）
 * - 自定义平台：校验必填 + 对 api_base_url 做一次 HEAD/GET 连通性探测，异常全部转成可读错误（绝不 500）
 */
class GenericAdapter implements PlatformAdapter
{
    public function __construct(
        protected string $platformKey,
        protected array $definition = []
    ) {
    }

    public function key(): string
    {
        return $this->platformKey;
    }

    public function capabilities(): array
    {
        return array_merge(
            ['pullOrders' => true, 'pushShipment' => true, 'ackOrder' => false],
            $this->definition['capabilities'] ?? []
        );
    }

    public function verifyCredentials(array $credentials, array $options = []): array
    {
        $required = $this->definition['required_credentials'] ?? [];
        $missing = [];
        foreach ($required as $field => $label) {
            if (trim((string) ($credentials[$field] ?? '')) === '') {
                $missing[] = $label;
            }
        }
        if ($missing) {
            return [
                'ok' => false,
                'code' => 'CREDENTIAL_MISSING',
                'message' => '缺少必填凭证：' . implode('、', $missing) . '。请到 ' . ($this->definition['apply_url'] ?? '平台开放平台') . ' 申请后重新填写。',
            ];
        }

        $baseUrl = $options['api_base_url'] ?? ($this->definition['api_base_url'] ?? null);
        if ($baseUrl) {
            if (! config('platforms.probe_enabled', true)) {
                return ['ok' => true, 'code' => 'OK', 'message' => '已跳过连通性探测（离线/测试模式），仅校验配置完整性。'];
            }

            return $this->probe($baseUrl);
        }

        return [
            'ok' => true,
            'code' => 'OK',
            'message' => '凭证完整性校验通过。订单拉取需配置接口地址（api_base_url），未配置时同步会返回可读错误。',
        ];
    }

    /** 连通性探测：任何异常都转成可读消息，不抛给控制器 */
    protected function probe(string $baseUrl): array
    {
        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return ['ok' => false, 'code' => 'URL_INVALID', 'message' => '接口地址不是合法 URL，请填写完整地址（含 https://）。'];
        }

        try {
            $response = Http::timeout(5)->withoutVerifying()->get($baseUrl);

            return [
                'ok' => true,
                'code' => 'OK',
                'message' => '接口连通性探测完成（HTTP ' . $response->status() . '），返回内容不代表业务可用；订单拉取仍为待接入。',
            ];
        } catch (\Throwable $e) {
            Log::warning('平台连通性自检失败', ['platform' => $this->platformKey, 'url' => $baseUrl, 'error' => $e->getMessage()]);

            return [
                'ok' => false,
                'code' => 'CONNECT_FAILED',
                'message' => '无法连接接口地址：' . mb_substr($e->getMessage(), 0, 120) . '。请核对地址、网络与白名单，或联系平台确认应用状态。',
            ];
        }
    }

    public function mapOrderFields(array $payload): array
    {
        $mapping = $this->definition['field_mapping'] ?? [];

        return $mapping ? $this->applyMapping($payload, $mapping) : $payload;
    }

    public function mapStatus(string $platformStatus): string
    {
        return $this->definition['status_mapping'][$platformStatus] ?? $platformStatus;
    }

    public function fetchOrders(array $credentials, array $options = []): array
    {
        $baseUrl = $this->baseUrl($options);
        if (! $baseUrl) {
            throw new \DomainException('该平台未配置订单接口地址（api_base_url），无法拉取订单。自定义平台请补全接口地址。');
        }

        try {
            $response = Http::withToken((string) ($credentials['api_key'] ?? ''))
                ->acceptJson()
                ->timeout((int) ($options['timeout'] ?? 10))
                ->get(rtrim($baseUrl, '/') . '/orders', array_filter([
                    'updated_since' => $options['updated_since'] ?? null,
                    'page_size' => $options['page_size'] ?? 100,
                ]));
        } catch (\Throwable $e) {
            throw new \DomainException('订单拉取请求失败：' . mb_substr($e->getMessage(), 0, 160));
        }

        if (! $response->successful()) {
            throw new \DomainException('订单拉取接口返回 HTTP ' . $response->status() . '：' . mb_substr($response->body(), 0, 160));
        }

        $decoded = $response->json();
        $orders = data_get($decoded, 'data.orders') ?? data_get($decoded, 'orders');
        if ($orders === null && is_array($decoded) && array_is_list($decoded)) {
            $orders = $decoded;
        }
        if (! is_array($orders)) {
            throw new \DomainException('订单拉取接口返回结构无法识别：期望 data.orders / orders 数组。');
        }

        return array_map(fn ($order) => $this->normalizeOrder((array) $order), $orders);
    }

    public function pushShipment(array $credentials, array $payload, array $options = []): array
    {
        $baseUrl = $this->baseUrl($options);
        if (! $baseUrl) {
            return ['ok' => false, 'message' => '该平台未配置接口地址（api_base_url），无法回写发货。'];
        }

        try {
            $response = Http::withToken((string) ($credentials['api_key'] ?? ''))
                ->acceptJson()
                ->timeout((int) ($options['timeout'] ?? 10))
                ->post(rtrim($baseUrl, '/') . '/shipments', $payload);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => '发货回写请求失败：' . mb_substr($e->getMessage(), 0, 160)];
        }

        if (! $response->successful()) {
            return ['ok' => false, 'message' => '发货回写接口返回 HTTP ' . $response->status() . '：' . mb_substr($response->body(), 0, 120)];
        }

        return ['ok' => true, 'message' => '发货已回写平台（HTTP ' . $response->status() . '）。'];
    }

    private function baseUrl(array $options): ?string
    {
        return $options['api_base_url'] ?? $this->definition['api_base_url'] ?? null;
    }

    /** 把平台/自定义字段统一成内部订单结构 */
    private function normalizeOrder(array $payload): array
    {
        $data = $this->mapOrderFields($payload);
        $rawStatus = (string) (data_get($data, 'status') ?? 'pending');
        $items = data_get($data, 'items');
        if (! is_array($items)) {
            $items = data_get($data, 'order_items', []);
        }

        return [
            'order_no' => (string) (data_get($data, 'order_no') ?? data_get($data, 'ecom_order_no') ?? data_get($data, 'tid') ?? ''),
            'status' => $this->mapStatus($rawStatus),
            'total_amount' => (float) (data_get($data, 'total_amount') ?? data_get($data, 'total_fee') ?? data_get($data, 'amount') ?? 0),
            'pay_amount' => (float) (data_get($data, 'pay_amount') ?? data_get($data, 'payment') ?? data_get($data, 'total_amount') ?? 0),
            'buyer_nick' => (string) (data_get($data, 'buyer_nick') ?? data_get($data, 'buyer_name') ?? ''),
            'receiver_name' => (string) (data_get($data, 'receiver_name') ?? data_get($data, 'receiver.name') ?? ''),
            'receiver_phone' => (string) (data_get($data, 'receiver_phone') ?? data_get($data, 'receiver.mobile') ?? ''),
            'receiver_address' => (string) (data_get($data, 'receiver_address') ?? data_get($data, 'receiver.address') ?? ''),
            'paid_at' => data_get($data, 'paid_at'),
            'shipped_at' => data_get($data, 'shipped_at'),
            'tracking_no' => (string) (data_get($data, 'tracking_no') ?? ''),
            'items' => array_map(fn ($item) => [
                'outer_sku' => (string) (data_get($item, 'outer_sku') ?? data_get($item, 'sku') ?? data_get($item, 'outer_id') ?? ''),
                'product_name' => (string) (data_get($item, 'product_name') ?? data_get($item, 'name') ?? data_get($item, 'title') ?? '未命名商品'),
                'qty' => (int) (data_get($item, 'qty') ?? data_get($item, 'quantity') ?? 1),
                'price' => (float) (data_get($item, 'price') ?? data_get($item, 'unit_price') ?? 0),
            ], is_array($items) ? $items : []),
            'raw' => $payload,
        ];
    }

    protected function applyMapping(array $payload, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $local => $remote) {
            data_set($out, $local, data_get($payload, $remote));
        }

        return $out;
    }
}
