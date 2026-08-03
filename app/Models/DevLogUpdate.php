<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['dev_log_id', 'update'])]
class DevLogUpdate extends Model
{
    use HasFactory;

    public function devLog(): BelongsTo
    {
        return $this->belongsTo(DevLog::class);
    }
}
