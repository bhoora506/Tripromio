<?php

namespace App\Enums;

/**
 * Lifecycle states of a connection request between two users.
 *
 * Allowed transitions:
 *   pending   → accepted | rejected | cancelled
 *   accepted  → (terminal)
 *   rejected  → (terminal)
 *   cancelled → (terminal)
 *
 * Re-request behaviour:
 *   A user may submit a fresh pending request after a rejected or cancelled one.
 *   Only one pending request per (requester_id, recipient_id) pair is allowed
 *   at a time. This invariant is enforced at the application layer.
 */
enum ConnectionStatus: string
{
    case Pending   = 'pending';
    case Accepted  = 'accepted';
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
            self::Accepted, self::Rejected, self::Cancelled => true,
            default                                         => false,
        };
    }
}
