<?php

namespace App\Enums;

enum IssueSeverity: string
{
    case Normal = 'normal';
    case Serious = 'serious';
    case Blocking = 'blocking';

    public function label(): string
    {
        return match ($this) {
            self::Normal => '一般',
            self::Serious => '严重',
            self::Blocking => '阻塞',
        };
    }
}
