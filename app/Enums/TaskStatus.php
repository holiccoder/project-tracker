<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待确认',
            self::Confirmed => '已确认',
            self::InProgress => '进行中',
            self::Done => '已完成',
            self::Accepted => '已验收',
            self::Rejected => '已拒绝',
            self::ChangesRequested => '要求返工',
        };
    }
}
