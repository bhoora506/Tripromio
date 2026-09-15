<?php

namespace App\Enums;

/**
 * Lifecycle states of a trip join request.
 *
 * Allowed transitions:
 *   pending   → approved | rejected | cancelled
 *   approved  → (terminal)
 *   rejected  → (terminal)
 *   cancelled → (terminal)
 *
 * A user may submit a fresh pending request after a rejected or cancelled request.
 * Only one pending request per (trip_id, user_id) pair is allowed at a time.
 * This invariant is enforced at the application layer.
 */
enum JoinRequestStatus: string
{
    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Cancelled = 'cancelled';

    /** @return string[] */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Returns true if this is a terminal state (no further transitions allowed).
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Approved, self::Rejected, self::Cancelled => true,
            default                                         => false,
        };
    }
}
