<?php

namespace Tests\Unit;

use App\Enums\TaskStatus;
use App\Models\Task;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TaskStateMachineTest extends TestCase
{
    private function makeTask(TaskStatus $status): Task
    {
        $task = new Task;
        $task->status = $status;

        return $task;
    }

    public function test_pending_can_be_confirmed_or_rejected(): void
    {
        $task = $this->makeTask(TaskStatus::Pending);

        $this->assertTrue($task->canTransitionTo(TaskStatus::Confirmed));
        $this->assertTrue($task->canTransitionTo(TaskStatus::Rejected));
        $this->assertFalse($task->canTransitionTo(TaskStatus::Done));
        $this->assertFalse($task->canTransitionTo(TaskStatus::Accepted));
    }

    public function test_illegal_transition_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeTask(TaskStatus::Pending)->transitionTo(TaskStatus::Done);
    }

    public function test_reject_requires_reason(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->makeTask(TaskStatus::Pending)->transitionTo(TaskStatus::Rejected);
    }

    public function test_rejected_and_accepted_are_terminal(): void
    {
        $rejected = $this->makeTask(TaskStatus::Rejected);
        $this->assertFalse($rejected->canTransitionTo(TaskStatus::InProgress));

        $accepted = $this->makeTask(TaskStatus::Accepted);
        $this->assertFalse($accepted->canTransitionTo(TaskStatus::InProgress));
    }

    public function test_done_can_be_accepted_or_request_changes(): void
    {
        $task = $this->makeTask(TaskStatus::Done);

        $this->assertTrue($task->canTransitionTo(TaskStatus::Accepted));
        $this->assertTrue($task->canTransitionTo(TaskStatus::ChangesRequested));
    }

    public function test_changes_requested_goes_back_to_in_progress(): void
    {
        $task = $this->makeTask(TaskStatus::ChangesRequested);

        $this->assertTrue($task->canTransitionTo(TaskStatus::InProgress));
    }

    public function test_confirmed_goes_to_in_progress_then_done(): void
    {
        $task = $this->makeTask(TaskStatus::Confirmed);
        $this->assertTrue($task->canTransitionTo(TaskStatus::InProgress));

        $task = $this->makeTask(TaskStatus::InProgress);
        $this->assertTrue($task->canTransitionTo(TaskStatus::Done));
    }
}
