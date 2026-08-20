<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'website_name',
    'login_url',
    'username',
    'password',
    'note',
])]
class Account extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * The password is encrypted at rest and decrypted on read,
     * so API responses can return the plain-text value for autofill.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
