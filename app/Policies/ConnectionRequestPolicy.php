<?php

namespace App\Policies;

use App\Models\ConnectionRequest;
use App\Models\User;

/**
 * Authorization policy for ConnectionRequest actions.
 *
 * Convention mirrors TripJoinRequestPolicy:
 *   - Policy returns false to produce a 403 Forbidden.
 *   - Business-rule conflicts (duplicate pending, wrong status, etc.)
 *     are thrown as HttpException(409) in ConnectionRequestService.
 */
class ConnectionRequestPolicy
{
    /**
     * Only the recipient may accept a connection request.
     */
    public function accept(User $user, ConnectionRequest $connectionRequest): bool
    {
        return $connectionRequest->recipient_id === $user->id;
    }

    /**
     * Only the recipient may reject a connection request.
     */
    public function reject(User $user, ConnectionRequest $connectionRequest): bool
    {
        return $connectionRequest->recipient_id === $user->id;
    }

    /**
     * Only the requester may cancel their own connection request.
     */
    public function cancel(User $user, ConnectionRequest $connectionRequest): bool
    {
        return $connectionRequest->requester_id === $user->id;
    }
}
