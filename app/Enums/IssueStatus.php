<?php

namespace App\Enums;

enum IssueStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => '待处理',
            self::InProgress => '处理中',
            self::Resolved => '已解决',
            self::Closed => '已关闭',
        };
    }
}
