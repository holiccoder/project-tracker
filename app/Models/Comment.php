<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable(['commentable_id', 'commentable_type', 'body', 'attachments', 'author_id', 'author_type'])]
class Comment extends Model
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
            'attachments' => 'array',
        ];
    }

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

    protected static function booted(): void
    {
        static::deleted(function (Comment $comment) {
            foreach ($comment->attachments ?? [] as $path) {
                if (is_string($path) && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }
        });
    }
}
