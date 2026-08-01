<?php

namespace Tests\Unit;

use App\Enums\IssueStatus;
use App\Models\Issue;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class IssueStateMachineTest extends TestCase
{
    public function test_open_goes_to_in_progress(): void
    {
        $issue = new Issue;
        $issue->status = IssueStatus::Open;

        $this->assertTrue($issue->canTransitionTo(IssueStatus::InProgress));
        $this->assertFalse($issue->canTransitionTo(IssueStatus::Resolved));
    }

    public function test_in_progress_goes_to_resolved(): void
    {
        $issue = new Issue;
        $issue->status = IssueStatus::InProgress;

        $this->assertTrue($issue->canTransitionTo(IssueStatus::Resolved));
    }

    public function test_resolved_goes_to_closed_and_is_terminal(): void
    {
        $issue = new Issue;
        $issue->status = IssueStatus::Resolved;

        $this->assertTrue($issue->canTransitionTo(IssueStatus::Closed));
        $this->assertFalse($issue->canTransitionTo(IssueStatus::Open));
    }

    public function test_illegal_transition_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $issue = new Issue;
        $issue->status = IssueStatus::Closed;
        $issue->transitionTo(IssueStatus::Open);
    }
}
