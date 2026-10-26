<?php

namespace App\Policies;

use App\Models\Transmittal;
use App\Models\User;

class TransmittalPolicy
{
    public function view(User $user, Transmittal $transmittal): bool
    {
        return $user->canApproveTransmittals()
            || $transmittal->prepared_by === $user->id;
    }

    public function viewAttachments(User $user, Transmittal $transmittal): bool
    {
        return $user->transmittal_role === 'client'
            || $this->view($user, $transmittal)
            || $transmittal->recipients()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
                ->exists();
    }

    public function approve(User $user, Transmittal $transmittal): bool
    {
        return $user->canApproveTransmittals()
            && $transmittal->prepared_by !== $user->id
            && $transmittal->status === 'pending_approval';
    }

    public function reject(User $user, Transmittal $transmittal): bool
    {
        return $user->canApproveTransmittals()
            && $transmittal->status === 'pending_approval';
    }

    public function transmit(User $user, Transmittal $transmittal): bool
    {
        $isAuthorizedSender = $user->canApproveTransmittals()
            || $transmittal->prepared_by === $user->id;

        return $isAuthorizedSender
            && in_array(
                $transmittal->status,
                ['pending_approval', 'approved', 'sent'],
                true
            );
    }

    public function uploadProof(User $user, Transmittal $transmittal): bool
    {
        $canManageDraftProof = $transmittal->prepared_by === $user->id
            && in_array(
                $transmittal->status,
                ['draft', 'pending_approval'],
                true
            );

        return $canManageDraftProof
            || (
                $user->canApproveTransmittals()
                && in_array(
                    $transmittal->status,
                    ['pending_approval', 'approved', 'sent', 'acknowledged'],
                    true
                )
            );
    }
}
