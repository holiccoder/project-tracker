<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

#[Fillable([
    'project_id',
    'created_by',
    'title',
    'description',
    'priority',
    'status',
    'due_date',
    'reject_reason',
    'completed_at',
    'accepted_at',
])]
class Task extends Model
{
    use HasFactory;

    /**
     * Allowed transitions per current status.
     *
     * @var array<string, array<int, TaskStatus>>
     */
    public const array TRANSITIONS = [
        'pending' => [TaskStatus::Confirmed, TaskStatus::Rejected],
        'confirmed' => [TaskStatus::InProgress],
        'in_progress' => [TaskStatus::Done],
        'done' => [TaskStatus::Accepted, TaskStatus::ChangesRequested],
        'changes_requested' => [TaskStatus::InProgress],
        'accepted' => [],
        'rejected' => [],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The customer who delegated the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function devLogs(): HasMany
    {
        return $this->hasMany(DevLog::class);
    }

    /**
     * Whether the given target status is a legal transition from the current one.
     */
    public function canTransitionTo(TaskStatus $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status->value] ?? [], true);
    }

    /**
     * Apply the state machine transition. Throws for illegal transitions.
     */
    public function transitionTo(TaskStatus $target, ?string $rejectReason = null): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! $this->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                "非法状态流转: {$this->status->value} → {$target->value}",
            );
        }

        if ($target === TaskStatus::Rejected && blank($rejectReason)) {
            throw new InvalidArgumentException('拒绝任务必须填写原因');
        }

        $this->status = $target;
        $this->reject_reason = $target === TaskStatus::Rejected ? $rejectReason : null;
        $this->completed_at = $target === TaskStatus::Done ? now() : $this->completed_at;
        $this->accepted_at = $target === TaskStatus::Accepted ? now() : $this->accepted_at;
        $this->save();
    }
}
