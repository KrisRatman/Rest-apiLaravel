<?php

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Relations\Pivot;

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
