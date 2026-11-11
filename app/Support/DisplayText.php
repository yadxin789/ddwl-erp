<?php

namespace App\Support;

/**
 * 页面显示翻译器。
 *
 * 系统内部仍以英文代码保存角色、权限、状态、类型等值；这些代码同时用于业务判断，
 * 不能在数据库或业务逻辑中改写。页面渲染前统一经本类查询“英文代码 → 中文词条”
 * 翻译集（lang/zh_CN/display.php），只改变显示文字，不改变任何存储值。
 *
 * 将来扩展多语言时，为对应 locale 增加一份 display.php 即可，视图无需改动。
 */
class DisplayText
{
    /** @var array<string, array<string, string>> */
    private static array $entries = [];

    /**
     * 读取当前语言的词条分组；当前语言缺少词条时回退中文词条。
     *
     * @return array<string, string>
     */
    private static function group(string $name): array
    {
        if (isset(self::$entries[$name])) {
            return self::$entries[$name];
        }

        $table = trans('display.' . $name);
        if (! is_array($table)) {
            $table = trans('display.' . $name, [], 'zh_CN');
        }

        return self::$entries[$name] = is_array($table) ? $table : [];
    }

    /**
     * 按英文代码查中文词条；词条不存在时原样返回代码，避免页面空白。
     */
    private static function lookup(string $group, string $code): string
    {
        return self::group($group)[$code] ?? $code;
    }

    public static function role(?string $name): string
    {
        if ($name === null || $name === '') {
            return '未分配';
        }

        return self::lookup('roles', $name);
    }

    /** 角色代码数组 → 中文、连接 */
    public static function roles(iterable $names, string $separator = '、'): string
    {
        $labels = [];
        foreach ($names as $name) {
            $labels[] = self::role((string) $name);
        }

        return $labels ? implode($separator, $labels) : '未分配';
    }

    /** 权限代码 → 中文（如 system.user.view => 系统·用户查看） */
    public static function permission(string $name): string
    {
        $actions = self::group('permission_actions');
        $parts = explode('.', $name);
        if (count($parts) === 1) {
            return $actions[$parts[0]] ?? $parts[0];
        }

        $module = self::lookup('permission_modules', $parts[0]);
        $labels = [];
        foreach (array_slice($parts, 1) as $part) {
            $labels[] = $actions[$part] ?? $part;
        }

        return $module . '·' . implode('', $labels);
    }

    public static function module(?string $key): string
    {
        if ($key === null || $key === '') {
            return '其他';
        }

        return self::lookup('permission_modules', $key);
    }

    public static function status(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::lookup('statuses', $value);
    }

    public static function type(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::lookup('types', $value);
    }

    public static function priority(?string $value): string
    {
        return self::type($value);
    }

    public static function level(?string $value): string
    {
        return self::type($value);
    }

    public static function stage(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return self::lookup('stages', strtolower($value));
    }
}
