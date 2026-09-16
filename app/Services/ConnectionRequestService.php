<?php

namespace App\Services;

use App\Enums\ConnectionStatus;
use App\Models\ConnectionRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Centralises all business rules for the ConnectionRequest lifecycle.
 *
 * Design mirrors TripJoinRequestService:
 *   - service throws HttpException for conflict/business-rule violations (409)
 *   - policy (ConnectionRequestPolicy) handles authorization (403)
 *   - controller is thin; delegates all logic here
 *
 * Connection states
 * ─────────────────
 * pending   → accepted  (recipient accepts)
 * pending   → rejected  (recipient rejects)
 * pending   → cancelled (requester cancels)
 *
 * Re-request rule
 * ───────────────
 * After a rejected or cancelled request, the requester may send a new pending
 * request to the same recipient. Only one pending request per directed
 * (requester_id, recipient_id) pair is allowed at any time.
 *
 * Self-request and opposite-direction pending
 * ─────────────────────────────────────────────
 * Both are rejected with 409 here.
 *
 * DB NOTE: No UNIQUE(requester_id, recipient_id) constraint exists.
 * All uniqueness invariants are enforced by this service.
 */
class ConnectionRequestService
{
    /**
     * Send a connection request.
     *
     * Rules enforced:
     *   1. Requester cannot send to themselves.
     *   2. Recipient must have is_discoverable = true (or no profile = treat as non-discoverable).
     *   3. Only one pending request may exist in either direction between the two users.
     *   4. Cannot send a request if an accepted connection already exists in either direction.
     *   5. Re-request after rejection/cancellation is allowed.
     *
     * @throws HttpException (409)
     */
    public function sendRequest(User $requester, User $recipient): ConnectionRequest
    {
        // Rule 1: no self-request
        if ($requester->id === $recipient->id) {
            throw new HttpException(409, 'You cannot send a connection request to yourself.');
        }

        // Rule 2: recipient must be discoverable
        $profile = $recipient->profile;
        if (! $profile || ! $profile->is_discoverable) {
            // 404 to avoid leaking whether the account exists but is hidden
            throw new HttpException(404, 'User not found.');
        }

        // Rule 3: no pending request already exists in either direction
        $pendingExists = ConnectionRequest::where('status', ConnectionStatus::Pending->value)
            ->where(function ($q) use ($requester, $recipient) {
                $q->where(function ($inner) use ($requester, $recipient) {
                    // A → B pending already
                    $inner->where('requester_id', $requester->id)
                          ->where('recipient_id', $recipient->id);
                })->orWhere(function ($inner) use ($requester, $recipient) {
                    // B → A pending already (opposite direction)
                    $inner->where('requester_id', $recipient->id)
                          ->where('recipient_id', $requester->id);
                });
            })
            ->exists();

        if ($pendingExists) {
            throw new HttpException(409, 'A pending connection request already exists between you and this user.');
        }

        // Rule 4: no accepted connection already exists in either direction
        $acceptedExists = ConnectionRequest::where('status', ConnectionStatus::Accepted->value)
            ->where(function ($q) use ($requester, $recipient) {
                $q->where(function ($inner) use ($requester, $recipient) {
                    $inner->where('requester_id', $requester->id)
                          ->where('recipient_id', $recipient->id);
                })->orWhere(function ($inner) use ($requester, $recipient) {
                    $inner->where('requester_id', $recipient->id)
                          ->where('recipient_id', $requester->id);
                });
            })
            ->exists();

        if ($acceptedExists) {
            throw new HttpException(409, 'You are already connected with this user.');
        }

        // Create the pending request
        return ConnectionRequest::create([
            'requester_id' => $requester->id,
            'recipient_id' => $recipient->id,
            'status'       => ConnectionStatus::Pending->value,
        ]);
    }

    /**
     * Accept a pending connection request (recipient only).
     *
     * Uses a DB transaction with a pessimistic lock on the request row
     * to prevent race conditions where two actions modify the same record
     * simultaneously (mirrors TripJoinRequestService::approve pattern).
     *
     * @throws HttpException (409)
     */
    public function accept(ConnectionRequest $connectionRequest): ConnectionRequest
    {
        return DB::transaction(function () use ($connectionRequest) {
            // Pessimistic lock on this row
            $locked = ConnectionRequest::where('id', $connectionRequest->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isPending()) {
                throw new HttpException(
                    409,
                    "Cannot accept a request that is already {$locked->status->value}."
                );
            }

            $locked->update(['status' => ConnectionStatus::Accepted->value]);

            return $locked->refresh();
        });
    }

    /**
     * Reject a pending connection request (recipient only).
     *
     * @throws HttpException (409)
     */
    public function reject(ConnectionRequest $connectionRequest): ConnectionRequest
    {
        if (! $connectionRequest->isPending()) {
            throw new HttpException(
                409,
                "Cannot reject a request that is already {$connectionRequest->status->value}."
            );
        }

        $connectionRequest->update(['status' => ConnectionStatus::Rejected->value]);

        return $connectionRequest->refresh();
    }

    /**
     * Cancel a pending connection request (requester only).
     *
     * @throws HttpException (409)
     */
    public function cancel(ConnectionRequest $connectionRequest): ConnectionRequest
    {
        if (! $connectionRequest->isPending()) {
            throw new HttpException(
                409,
                "Cannot cancel a request that is already {$connectionRequest->status->value}."
            );
        }

        $connectionRequest->update(['status' => ConnectionStatus::Cancelled->value]);

        return $connectionRequest->refresh();
    }
}
