<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;

final class ReconcileUserGroupMemberships extends Command
{
    protected $signature = 'filament-lms:reconcile-user-group-memberships';

    protected $description = 'Rebuild published memberships for all active LMS user groups';

    public function handle(
        UserGroupCriteriaRegistry $registry,
        UserGroupMembershipSynchronizer $synchronizer,
    ): int {
        if (! $registry->enabled()) {
            $this->warn('User groups are not enabled (no criteria provider configured).');

            return self::SUCCESS;
        }

        $count = $synchronizer->reconcileAll();
        $this->info("Reconciled {$count} user group(s).");

        return self::SUCCESS;
    }
}
