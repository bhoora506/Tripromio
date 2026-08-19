<?php

namespace App\Enums;

/**
 * Role of a user within a trip.
 *
 * owner  — the user who created the trip.
 *          Only one owner row per trip is enforced by application logic.
 * member — a user who joined after an accepted connection request.
 */
enum MemberRole: string
{
    case Owner  = 'owner';
    case Member = 'member';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
