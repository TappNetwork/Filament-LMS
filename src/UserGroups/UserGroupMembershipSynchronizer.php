<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Jobs\RebuildUserGroupMemberships;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\Models\UserGroupMembership;
use Tapp\FilamentLms\Services\CourseAssignmentNotifier;
use Throwable;

final class UserGroupMembershipSynchronizer
{
    public function __construct(
        private readonly UserGroupRuleMatcher $matcher,
        private readonly CourseAssignmentNotifier $assignments,
    ) {}

    public function queueRebuild(UserGroup $group): void
    {
        if (! config('filament-lms.user_groups.sync_queue', true)) {
            $this->rebuild($group);

            return;
        }

        $group->forceFill([
            'sync_status' => 'queued',
            'sync_error' => null,
        ])->save();

        RebuildUserGroupMemberships::dispatch($group->id);
    }

    public function rebuild(UserGroup $group): void
    {
        $group->forceFill([
            'sync_status' => 'syncing',
            'sync_error' => null,
        ])->save();

        $pendingAssignments = [];

        try {
            $desiredRevision = $group->published_revision + 1;
            $matchedAt = now();
            $userIds = $this->matcher
                ->matchingUsersQuery($group->rules ?? [])
                ->pluck($this->userKeyName())
                ->all();
            $pendingAssignments = $this->assignments->assignmentsForNewMembers(
                $group,
                $this->addedUserIds($group, $userIds),
            );

            DB::transaction(function () use ($group, $desiredRevision, $userIds, $matchedAt): void {
                $now = now();
                $rows = [];

                foreach ($userIds as $userId) {
                    $rows[] = [
                        'user_group_id' => $group->id,
                        'user_id' => $userId,
                        'revision' => $desiredRevision,
                        'matched_at' => $matchedAt,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    if (count($rows) >= 500) {
                        DB::table('lms_user_group_memberships')->upsert(
                            $rows,
                            ['user_group_id', 'user_id', 'revision'],
                            ['matched_at', 'updated_at'],
                        );
                        $rows = [];
                    }
                }

                if ($rows !== []) {
                    DB::table('lms_user_group_memberships')->upsert(
                        $rows,
                        ['user_group_id', 'user_id', 'revision'],
                        ['matched_at', 'updated_at'],
                    );
                }

                $group->forceFill([
                    'published_revision' => $desiredRevision,
                    'sync_status' => 'idle',
                    'sync_error' => null,
                    'last_synced_at' => $now,
                ])->save();

                DB::table('lms_user_group_memberships')
                    ->where('user_group_id', $group->id)
                    ->where('revision', '<', $desiredRevision)
                    ->delete();
            });
        } catch (Throwable $exception) {
            $group->forceFill([
                'sync_status' => 'failed',
                'sync_error' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }

        $this->assignments->dispatchAssignments($pendingAssignments);
    }

    public function refreshUser(int|string $userId): void
    {
        $groups = UserGroup::query()
            ->where('is_active', true)
            ->get();

        /** @var class-string<Model> $userModel */
        $userModel = config('filament-lms.user_model');
        $user = $userModel::query()->find($userId);

        if ($user === null) {
            UserGroupMembership::query()
                ->where('user_id', $userId)
                ->delete();

            return;
        }

        foreach ($groups as $group) {
            $matches = $this->matcher->userMatches($user, $group->rules ?? []);
            $revision = max(1, (int) $group->published_revision);

            if ($group->published_revision < 1) {
                $this->rebuild($group);

                continue;
            }

            $exists = DB::table('lms_user_group_memberships')
                ->where('user_group_id', $group->id)
                ->where('user_id', $userId)
                ->where('revision', $revision)
                ->exists();

            if ($matches && ! $exists) {
                $pendingAssignments = $this->assignments->assignmentsForNewMembers($group, [$userId]);

                DB::table('lms_user_group_memberships')->upsert([
                    [
                        'user_group_id' => $group->id,
                        'user_id' => $userId,
                        'revision' => $revision,
                        'matched_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ], ['user_group_id', 'user_id', 'revision'], ['matched_at', 'updated_at']);

                $this->assignments->dispatchAssignments($pendingAssignments);
            }

            if (! $matches && $exists) {
                DB::table('lms_user_group_memberships')
                    ->where('user_group_id', $group->id)
                    ->where('user_id', $userId)
                    ->where('revision', $revision)
                    ->delete();
            }
        }
    }

    public function reconcileAll(): int
    {
        $count = 0;

        UserGroup::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (UserGroup $group) use (&$count): void {
                $this->rebuild($group);
                $count++;
            });

        return $count;
    }

    /**
     * @param  array<int, int|string>  $matchedUserIds
     * @return list<int|string>
     */
    private function addedUserIds(UserGroup $group, array $matchedUserIds): array
    {
        $matchedUserIds = $this->assignments->normalizeIds($matchedUserIds);

        if ($group->published_revision < 1) {
            return $matchedUserIds;
        }

        $previousUserIds = $this->assignments->normalizeIds(
            UserGroupMembership::query()
                ->where('user_group_id', $group->getKey())
                ->where('revision', $group->published_revision)
                ->pluck('user_id')
                ->all()
        );

        return array_values(array_diff($matchedUserIds, $previousUserIds));
    }

    private function userKeyName(): string
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-lms.user_model');

        return (new $userModel)->getQualifiedKeyName();
    }
}
