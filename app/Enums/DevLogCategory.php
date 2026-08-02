<?php

namespace App\Enums;

enum DevLogCategory: string
{
    case AgentIndependent = 'agent_independent';
    case HumanAgentCollaboration = 'human_agent_collaboration';

    public function label(): string
    {
        return match ($this) {
            self::AgentIndependent => 'Agent独立完成',
            self::HumanAgentCollaboration => '需要有人和Agent共同完成',
        };
    }
}
