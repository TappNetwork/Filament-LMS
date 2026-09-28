<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Observers;

use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentLms\Jobs\RefreshUserGroupMembershipsForUser;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;

final class UserGroupMembershipUserObserver
{
    public function __construct(
        private readonly UserGroupCriteriaRegistry $registry,
    ) {}

    public function saved(Model $user): void
    {
        $this->queueRefresh($user);
    }

    public function deleted(Model $user): void
    {
        $this->queueRefresh($user);
    }

    private function queueRefresh(Model $user): void
    {
        if (! $this->registry->enabled()) {
            return;
        }

        if (! config('filament-lms.user_groups.refresh_on_user_save', true)) {
            return;
        }

        RefreshUserGroupMembershipsForUser::dispatch($user->getKey());
    }
}
