<?php

namespace App\Enums;

enum ProjectUserRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Owner => '主联系人',
            self::Member => '成员',
        };
    }
}
