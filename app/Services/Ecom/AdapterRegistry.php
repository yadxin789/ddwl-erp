<?php

namespace App\Services\Ecom;

use App\Services\Ecom\Adapters\GenericAdapter;
use App\Services\Ecom\Contracts\PlatformAdapter;

/**
 * P1-07 适配器注册表：预置平台来自 config/platforms.php，自定义平台（custom:xxx）按实例配置生成。
 */
class AdapterRegistry
{
    public function presets(): array
    {
        return config('platforms.presets', []);
    }

    public function preset(string $key): ?array
    {
        return $this->presets()[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return str_starts_with($key, 'custom:') || $this->preset($key) !== null;
    }

    public function make(string $key, array $overrides = []): ?PlatformAdapter
    {
        if (str_starts_with($key, 'custom:')) {
            return new GenericAdapter($key, array_merge([
                'name' => $key,
                'region' => 'custom',
                'required_credentials' => ['api_key' => 'API Key / Token'],
                'field_mapping' => $overrides['field_mapping'] ?? [],
                'status_mapping' => $overrides['status_mapping'] ?? [],
                'api_base_url' => $overrides['api_base_url'] ?? null,
            ], $overrides));
        }

        $definition = $this->preset($key);
        if (! $definition) {
            return null;
        }

        return new GenericAdapter($key, $definition);
    }

    /** 国内/国外分组（向导第 1 步） */
    public function grouped(): array
    {
        $groups = ['domestic' => [], 'overseas' => []];
        foreach ($this->presets() as $key => $definition) {
            $region = $definition['region'] ?? 'domestic';
            $groups[$region][$key] = $definition;
        }

        return $groups;
    }
}
