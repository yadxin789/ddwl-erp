<?php
namespace App\Enums;
enum CrmOpportunityStage: string {
    case Prospecting = 'prospecting';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';
    public function label(): string {
        return match($this) { self::Prospecting => '初步接洽', self::Negotiation => '商务谈判', self::Won => '赢单', self::Lost => '输单' };
    }
}
