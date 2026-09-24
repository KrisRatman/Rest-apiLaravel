<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TeamRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * @return BelongsToMany<Team, $this, Membership, 'membership'>
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->using(Membership::class)
            ->as('membership')
            ->withPivot('id', 'role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assignee_id');
    }

    /**
     * Роль пользователя в команде или null, если он в ней не состоит.
     */
    public function roleIn(Team|int $team): ?TeamRole
    {
        $teamId = $team instanceof Team ? $team->getKey() : $team;

        // Роль проверяется политиками на каждом запросе, поэтому держим её в кэше.
        // Пустая строка — «не состоит»: её тоже кэшируем, чтобы чужие не били в базу.
        // Сбрасывается событиями Membership при любом изменении состава команды.
        $role = Cache::remember(
            Membership::cacheKey($teamId, $this->getKey()),
            config('api.cache_ttl.team_role'),
            fn () => Membership::query()
                ->where('team_id', $teamId)
                ->where('user_id', $this->getKey())
                ->first(['role'])
                ?->role->value ?? '',
        );

        return TeamRole::tryFrom($role);
    }
}
