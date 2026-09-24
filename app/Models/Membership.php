<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\Cache;

/**
 * Членство пользователя в команде (строка таблицы team_user).
 *
 * @property TeamRole $role
 */
class Membership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;

    /**
     * attach, detach и updateExistingPivot на связях с using(Membership::class)
     * проходят через эту модель, поэтому любое изменение состава или роли
     * сбрасывает закэшированную роль (см. User::roleIn).
     */
    protected static function booted(): void
    {
        $forget = fn (Membership $membership) => Cache::forget(
            self::cacheKey($membership->team_id, $membership->user_id),
        );

        static::saved($forget);
        static::deleted($forget);
    }

    public static function cacheKey(int $teamId, int $userId): string
    {
        return "team:{$teamId}:member:{$userId}:role";
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
