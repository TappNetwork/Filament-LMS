<div class="flex flex-col gap-8">
    @if ($showUsers)
        @livewire(\Tapp\FilamentLms\RelationManagers\CourseUsersRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => $pageClass,
        ], key('course-users-'.$course->getKey()))
    @endif

    @if ($showGroups)
        @livewire(\Tapp\FilamentLms\RelationManagers\CourseUserGroupsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => $pageClass,
        ], key('course-user-groups-'.$course->getKey()))
    @endif
</div>
