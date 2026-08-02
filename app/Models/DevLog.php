<?php

namespace App\Models;

use App\Enums\DevLogCategory;
use App\Enums\DevLogStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'date', 'content', 'status', 'category'])]
class DevLog extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DevLogStatus::class,
            'category' => DevLogCategory::class,
            'date' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected static function booted(): void
    {
        static::created(function (DevLog $devLog) {
            $project = $devLog->project;
            if ($project) {
                foreach ($project->members as $member) {
                    $member->notify(new \App\Notifications\NewDevLogNotification($devLog));
                }
            }
        });
    }
}
