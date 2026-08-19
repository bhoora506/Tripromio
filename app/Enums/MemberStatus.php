<?php

namespace App\Enums;

/**
 * Membership status of a user in a trip.
 *
 * active  — current active member.
 * left    — member voluntarily left the trip.
 * removed — member was removed by the trip owner.
 *
 * NOTE: Request/invitation workflow states (pending, accepted, rejected, cancelled)
 * are intentionally NOT stored here. Those belong to a separate trip_requests
 * entity that will be implemented in Phase 4 (Connections).
 * trip_members represents actual, confirmed membership only.
 */
enum MemberStatus: string
{
    case Active  = 'active';
    case Left    = 'left';
    case Removed = 'removed';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
