<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['commentable_id', 'commentable_type', 'body', 'author_id', 'author_type'])]
class Comment extends Model
{
    use HasFactory;

    /**
     * Get the owning commentable model (Task or Issue, etc.).
     */
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the polymorphic author who wrote the comment (User or Admin).
     */
    public function author(): MorphTo
    {
        return $this->morphTo();
    }
}
