<?php

namespace App\Enums;

enum DevLogStatus: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => '进行中',
            self::Completed => '已完成',
        };
    }
}
