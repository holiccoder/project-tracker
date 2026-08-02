<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['task_id', 'from_status', 'to_status', 'operator_id', 'operator_type', 'remark'])]
class TaskStatusHistory extends Model
{
    use HasFactory;

    /**
     * Only created_at timestamp is needed.
     */
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the polymorphic operator (User or Admin, nullable).
     */
    public function operator(): MorphTo
    {
        return $this->morphTo();
    }
}
