<?php

namespace App\Http\Controllers;

use App\Models\Ecom\PlatformInstance;
use App\Models\Inventory\Warehouse;
use App\Services\Ecom\AdapterRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * P1-07 电商接入：向导（三步）+ 平台设置页 + 手动添加任意平台
 * 本期只交付"配得对、连得上、可扩展"，订单拉取/发货回写默认关闭。
 */
class EcomOnboardingController extends Controller
{
    public function __construct(protected AdapterRegistry $registry)
    {
    }

    /** 第 1 步：选平台（国内/国外分组 + 搜索） */
    public function index(Request $request)
    {
        $groups = $this->registry->grouped();
        $keyword = trim((string) $request->query('q'));
        if ($keyword !== '') {
            foreach ($groups as $region => $items) {
                $groups[$region] = array_filter($items, fn ($p) => mb_stripos($p['name'], $keyword) !== false || mb_stripos((string) ($p['apply_url'] ?? ''), $keyword) !== false);
            }
        }
        $instances = PlatformInstance::orderByDesc('id')->limit(20)->get();

        return view('ecom.onboarding', compact('groups', 'instances', 'keyword'));
    }

    /** 第 2 步：凭证与参数（逐项外链申请页） */
    public function credentials(string $platformKey)
    {
        $definition = $this->registry->preset($platformKey);
        abort_if(! $definition, 404, '平台不存在');
        $warehouses = Warehouse::orderBy('warehouse_code')->get();
        $frequencies = config('platforms.sync_frequencies');

        return view('ecom.onboarding-credentials', compact('platformKey', 'definition', 'warehouses', 'frequencies'));
    }

    /** 第 3 步：连接自检 + 保存（失败返回 200 + 可读错误，不抛 500） */
    public function store(Request $request, string $platformKey)
    {
        $definition = $this->registry->preset($platformKey);
        abort_if(! $definition, 404, '平台不存在');

        $validator = Validator::make($request->all(), [
            'instance_name' => ['required', 'string', 'max:64'],
            'credentials' => ['required', 'array'],
            'default_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'sync_frequency' => ['required', 'in:' . implode(',', array_keys(config('platforms.sync_frequencies')))],
            'callback_url' => ['nullable', 'string', 'max:256'],
            'expires_at' => ['nullable', 'date'],
            'mapping_json' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return $this->backToForm($platformKey, $validator->errors()->toArray(), $request);
        }

        $credentials = array_filter((array) $request->input('credentials', []), fn ($v) => trim((string) $v) !== '');
        $adapter = $this->registry->make($platformKey);
        $check = $adapter->verifyCredentials($credentials);

        if (! $check['ok']) {
            return $this->backToForm($platformKey, ['credentials' => $check['message']], $request);
        }

        $instance = PlatformInstance::updateOrCreate(
            ['platform_key' => $platformKey],
            [
                'name' => $request->input('instance_name'),
                'region' => $definition['region'] ?? 'domestic',
                'credentials_json' => $credentials,
                'mapping_json' => $this->parseMapping($request->input('mapping_json')),
                'callback_url' => $request->input('callback_url'),
                'default_warehouse_id' => $request->input('default_warehouse_id'),
                'sync_frequency' => $request->input('sync_frequency'),
                'status' => true,
                'last_check_at' => now(),
                'last_check_result' => 'ok',
                'last_check_message' => $check['message'],
                'expires_at' => $request->input('expires_at'),
                'created_by' => Auth::id(),
            ]
        );
        Log::info('电商平台接入保存', ['platform' => $platformKey, 'instance_id' => $instance->id, 'user_id' => Auth::id()]);

        return redirect()->route('ecom.instances')->with('success', "「{$definition['name']}」接入完成：{$check['message']}");
    }

    /** 手动添加任意平台（自定义 REST 适配器） */
    public function customForm()
    {
        $warehouses = Warehouse::orderBy('warehouse_code')->get();
        $frequencies = config('platforms.sync_frequencies');
        $authTypes = config('platforms.auth_types');

        return view('ecom.onboarding-custom', compact('warehouses', 'frequencies', 'authTypes'));
    }

    public function customStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:64'],
            'platform_key' => ['required', 'string', 'max:32', 'alpha_dash', 'unique:platform_instances,platform_key'],
            'api_base_url' => ['required', 'string', 'max:256'],
            'auth_type' => ['required', 'in:' . implode(',', array_keys(config('platforms.auth_types')))],
            'api_key' => ['nullable', 'string', 'max:512'],
            'region' => ['required', 'in:domestic,overseas,custom'],
            'default_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'sync_frequency' => ['required', 'in:' . implode(',', array_keys(config('platforms.sync_frequencies')))],
            'field_mapping' => ['nullable', 'string', 'max:4000'],
            'status_mapping' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $key = 'custom:' . $request->input('platform_key');
        $mapping = $this->parseMapping($request->input('field_mapping'));
        $statusMapping = $this->parseMapping($request->input('status_mapping'));
        $credentials = ['api_key' => (string) $request->input('api_key')];

        $adapter = $this->registry->make($key, [
            'api_base_url' => $request->input('api_base_url'),
            'field_mapping' => $mapping,
            'status_mapping' => $statusMapping,
        ]);
        $check = $adapter->verifyCredentials($credentials, ['api_base_url' => $request->input('api_base_url')]);

        if (! $check['ok']) {
            return back()->withErrors(['api_base_url' => $check['message']])->withInput();
        }

        PlatformInstance::create([
            'platform_key' => $key,
            'name' => $request->input('name'),
            'region' => $request->input('region'),
            'credentials_json' => $credentials,
            'mapping_json' => ['field_mapping' => $mapping, 'status_mapping' => $statusMapping],
            'api_base_url' => $request->input('api_base_url'),
            'auth_type' => $request->input('auth_type'),
            'default_warehouse_id' => $request->input('default_warehouse_id'),
            'sync_frequency' => $request->input('sync_frequency'),
            'status' => true,
            'last_check_at' => now(),
            'last_check_result' => 'ok',
            'last_check_message' => $check['message'],
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('ecom.instances')->with('success', "自定义平台「{$request->input('name')}」已保存并完成连通性自检，Registry 已可识别 {$key}。");
    }

    /** 平台设置页：启停 / 默认仓 / 同步频率 / 自检 / 到期提醒 */
    public function instances()
    {
        $instances = PlatformInstance::with('warehouse')->orderBy('region')->orderByDesc('id')->get();
        $presets = $this->registry->presets();
        $expiring = $instances->filter(fn ($i) => $i->isExpiringSoon())->count();

        return view('ecom.instances', compact('instances', 'presets', 'expiring'));
    }

    public function toggle(Request $request, int $instance)
    {
        $data = $request->validate([
            'status' => ['required', 'boolean'],
            'sync_frequency' => ['nullable', 'in:' . implode(',', array_keys(config('platforms.sync_frequencies')))],
            'default_warehouse_id' => ['nullable', 'exists:warehouses,id'],
        ]);
        $model = PlatformInstance::findOrFail($instance);
        $model->update([
            'status' => (bool) $data['status'],
            'sync_frequency' => $data['sync_frequency'] ?? $model->sync_frequency,
            'default_warehouse_id' => $data['default_warehouse_id'] ?? $model->default_warehouse_id,
        ]);
        Log::info('电商平台实例启停', ['instance_id' => $model->id, 'status' => $model->status, 'user_id' => Auth::id()]);

        return back()->with('success', $model->status ? '平台已启用（订单拉取仍为待接入）。' : '平台已停用：不再产生任何同步任务。');
    }

    /** 渲染表单并带错误（200，不抛 500） */
    private function backToForm(string $platformKey, array $errors, Request $request)
    {
        $definition = $this->registry->preset($platformKey);
        $warehouses = Warehouse::orderBy('warehouse_code')->get();
        $frequencies = config('platforms.sync_frequencies');

        return response()->view('ecom.onboarding-credentials', [
            'platformKey' => $platformKey,
            'definition' => $definition,
            'warehouses' => $warehouses,
            'frequencies' => $frequencies,
            'errors' => new \Illuminate\Support\MessageBag($errors),
            'old' => $request->all(),
        ], 200);
    }

    private function parseMapping(?string $json): array
    {
        if (! $json) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['__invalid__' => json_last_error_msg()];
        }

        return is_array($decoded) ? $decoded : [];
    }
}
