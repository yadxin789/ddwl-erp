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
            ['pullOrders' => false, 'pushShipment' => false, 'ackOrder' => false],
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
            'message' => '凭证完整性校验通过。本期只做接入，真实订单拉取默认关闭（待接入）。',
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

    protected function applyMapping(array $payload, array $mapping): array
    {
        $out = [];
        foreach ($mapping as $local => $remote) {
            data_set($out, $local, data_get($payload, $remote));
        }

        return $out;
    }
}
