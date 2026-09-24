<?php

namespace App\Enums;

/**
 * Роль пользователя внутри конкретной команды.
 */
enum TeamRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';

    /**
     * Может ли роль управлять участниками, проектами и метками команды.
     */
    public function canManageTeam(): bool
    {
        return $this === self::Owner || $this === self::Admin;
    }

    /**
     * Роли, которые можно выдать при добавлении участника или смене роли.
     * Владелец назначается только через передачу владения.
     *
     * @return list<string>
     */
    public static function assignable(): array
    {
        return [self::Admin->value, self::Member->value];
    }
}
