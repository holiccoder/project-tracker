<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use App\Enums\ProjectUserRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'description',
    'status',
    'amount',
    'paid_amount',
    'deadline',
    'repo_url',
    'created_by',
    'remark',
])]
#[Hidden([])]
class Project extends Model
{
    use HasFactory;

    /**
     * Projects are addressed by their URL-friendly slug in client routes.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'deadline' => 'date',
        ];
    }

    /**
     * The developer who created the project.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    /**
     * The customers attached to the project.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_user')
            ->withPivot(['role', 'can_view_price'])
            ->withTimestamps();
    }

    /**
     * Whether the given member may view this project's financial details.
     */
    public function canViewPriceFor(User $user): bool
    {
        if ($this->pivot !== null && array_key_exists('can_view_price', $this->pivot->getAttributes())) {
            return (bool) $this->pivot->can_view_price;
        }

        $member = $this->relationLoaded('members')
            ? $this->members->firstWhere('id', $user->getKey())
            : $this->members()->whereKey($user->getKey())->first();

        return $member !== null && (bool) $member->pivot->can_view_price;
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function devLogs(): HasMany
    {
        return $this->hasMany(DevLog::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    /**
     * Whether the given user is a member of this project.
     */
    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * The role of the given user within this project, or null if not a member.
     */
    public function roleOf(User $user): ?ProjectUserRole
    {
        $pivot = $this->members()->whereKey($user->getKey())->first()?->pivot;

        return $pivot ? ProjectUserRole::tryFrom($pivot->role) : null;
    }

    /**
     * The outstanding amount (amount - paid_amount). Never stored.
     */
    public function getUnpaidAmountAttribute(): ?string
    {
        if ($this->amount === null) {
            return null;
        }

        return number_format(
            (float) $this->amount - (float) $this->paid_amount,
            2,
            '.',
            '',
        );
    }
}
