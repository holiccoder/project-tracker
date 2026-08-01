<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Active = 'active';
    case Delivered = 'delivered';
    case Paused = 'paused';

    public function label(): string
    {
        return match ($this) {
            self::Active => '进行中',
            self::Delivered => '已交付',
            self::Paused => '已暂停',
        };
    }
}
