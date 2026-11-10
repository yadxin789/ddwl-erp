<?php
namespace App\Enums;
enum InspectionStage: string {
    case IQC = 'IQC';
    case IPQC = 'IPQC';
    case FQC = 'FQC';
    case OQC = 'OQC';
    public function label(): string {
        return match($this) { self::IQC => '来料检验', self::IPQC => '制程检验', self::FQC => '成品检验', self::OQC => '出货检验' };
    }
}
