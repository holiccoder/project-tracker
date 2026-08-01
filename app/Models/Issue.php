<?php

namespace App\Models;

use App\Enums\IssueSeverity;
use App\Enums\IssueStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

#[Fillable(['project_id', 'title', 'description', 'severity', 'status', 'created_by', 'resolved_at'])]
class Issue extends Model
{
    use HasFactory;

    /**
     * @var array<string, array<int, IssueStatus>>
     */
    public const array TRANSITIONS = [
        'open' => [IssueStatus::InProgress],
        'in_progress' => [IssueStatus::Resolved],
        'resolved' => [IssueStatus::Closed],
        'closed' => [],
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'severity' => IssueSeverity::class,
            'status' => IssueStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * The developer who created the issue.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function canTransitionTo(IssueStatus $target): bool
    {
        return in_array($target, self::TRANSITIONS[$this->status->value] ?? [], true);
    }

    public function transitionTo(IssueStatus $target): void
    {
        if ($this->status === $target) {
            return;
        }

        if (! $this->canTransitionTo($target)) {
            throw new InvalidArgumentException(
                "非法状态流转: {$this->status->value} → {$target->value}",
            );
        }

        $this->status = $target;
        $this->resolved_at = $target === IssueStatus::Resolved ? now() : $this->resolved_at;
        $this->save();
    }
}
