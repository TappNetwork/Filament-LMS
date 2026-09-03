<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;

final class RebuildUserGroupMemberships implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $userGroupId,
    ) {}

    public function handle(UserGroupMembershipSynchronizer $synchronizer): void
    {
        $group = UserGroup::query()->find($this->userGroupId);

        if ($group === null || ! $group->is_active) {
            return;
        }

        $synchronizer->rebuild($group);
    }
}
