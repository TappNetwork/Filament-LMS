<?php

namespace Tapp\FilamentLms\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Tapp\FilamentLms\Models\Course;

class CertificatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can view the certificate.
     */
    public function view(User $user, Course $course, ?User $targetUser = null): bool
    {
        // If no target user is specified, use the authenticated user
        $targetUser = $targetUser ?? $user;

        // If the user is viewing their own certificate, they need to have completed the course
        if ($user->id === $targetUser->id) {
            return $course->completedByUserAt($user->id) !== null;
        }

        // If the user is viewing someone else's certificate, they need to be able to manage certificates
        return $this->manage($user);
    }

    /**
     * Determine if the user can manage certificates.
     */
    public function manage(User $user): bool
    {
        // This should be customized based on your application's needs
        // For example, you might want to check for specific roles or permissions
        return $user->hasRole('Admin');
    }
} 