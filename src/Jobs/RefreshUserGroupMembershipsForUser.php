<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;

final class RefreshUserGroupMembershipsForUser implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int|string $userId,
    ) {}

    public function handle(
        UserGroupCriteriaRegistry $registry,
        UserGroupMembershipSynchronizer $synchronizer,
    ): void {
        if (! $registry->enabled()) {
            return;
        }

        $synchronizer->refreshUser($this->userId);
    }
}
