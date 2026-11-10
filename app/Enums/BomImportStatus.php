<?php

namespace App\Enums;

enum BomImportStatus: string
{
    case Uploaded = 'uploaded';
    case Parsed = 'parsed';
    case Confirmed = 'confirmed';
    case Imported = 'imported';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => '已上传',
            self::Parsed => '已解析',
            self::Confirmed => '已确认',
            self::Imported => '已导入',
        };
    }

    public static function options(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }

        return $result;
    }
}
