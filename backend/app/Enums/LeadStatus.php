<?php

namespace App\Enums;

enum LeadStatus: string
{
    case Pending = 'pending';      // 待處理
    case Intake = 'intake';        // 已進件（內部進件/準備）
    case Approved = 'approved';    // 已核准（內部核准）
    case Submitted = 'submitted';  // 已送件（對外送件）
    case Disbursed = 'disbursed';  // 已撥款
    case Tracking = 'tracking';    // 追蹤中
    case Blacklist = 'blacklist';  // 黑名單

    public static function values(): array
    {
        return array_map(fn($c) => $c->value, self::cases());
    }
}