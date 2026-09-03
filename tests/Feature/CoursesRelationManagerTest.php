<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\RelationManagers\CoursesRelationManager;
use Tapp\FilamentLms\RelationManagers\CourseUsersRelationManager;
use Tapp\FilamentLms\Tests\Support\TestUserGroupCriteriaProvider;
use Tapp\FilamentLms\Tests\TestUser;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;

test('courses relation manager is configured for the courses relationship', function (): void {
    $reflection = new ReflectionClass(CoursesRelationManager::class);

    expect(CoursesRelationManager::getRelationshipName())->toBe('courses')
        ->and($reflection->getStaticPropertyValue('title'))->toBe('Assigned Courses')
        ->and($reflection->getStaticPropertyValue('modelLabel'))->toBe('Course');
});

test('user can attach and detach courses via the courses relationship', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    $user->courses()->attach($course->id);

    expect($user->courses()->pluck('lms_courses.id')->all())->toBe([$course->id]);

    $user->courses()->detach($course->id);

    expect($user->courses()->count())->toBe(0);
});

test('attaching the same course twice does not create duplicate pivot rows', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'duplicate@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();

    $user->courses()->syncWithoutDetaching([$course->id]);
    $user->courses()->syncWithoutDetaching([$course->id]);

    expect(DB::table('lms_course_user')
        ->where('user_id', $user->id)
        ->where('course_id', $course->id)
        ->count())->toBe(1);
});

test('attach query excludes courses already assigned to the user', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'exclude@example.com',
        'password' => bcrypt('password'),
    ]);

    $assignedCourse = Course::factory()->create(['name' => 'Assigned Course']);
    $availableCourse = Course::factory()->create(['name' => 'Available Course']);

    $user->courses()->attach($assignedCourse->id);

    $existingCourseIds = $user->courses()->pluck('course_id');

    $attachableCourseIds = Course::query()
        ->whereNotIn('lms_courses.id', $existingCourseIds)
        ->pluck('id')
        ->all();

    expect($attachableCourseIds)->toContain($availableCourse->id)
        ->and($attachableCourseIds)->not->toContain($assignedCourse->id);
});

test('courses relationship pivot created_at reflects assignment not course creation', function (): void {
    $user = TestUser::create([
        'name' => 'Test User',
        'email' => 'assigned-at@example.com',
        'password' => bcrypt('password'),
    ]);

    $courseCreatedAt = now()->subDays(30);
    $assignedAt = now()->subDays(2);

    $course = Course::factory()->create();
    $course->forceFill([
        'created_at' => $courseCreatedAt,
        'updated_at' => $courseCreatedAt,
    ])->save();

    $user->courses()->attach($course->id);

    DB::table('lms_course_user')
        ->where('user_id', $user->id)
        ->where('course_id', $course->id)
        ->update([
            'created_at' => $assignedAt,
            'updated_at' => $assignedAt,
        ]);

    $attachedCourse = $user->courses()->first();

    expect($attachedCourse->created_at->format('Y-m-d H:i:s'))
        ->toBe($courseCreatedAt->format('Y-m-d H:i:s'))
        ->and($attachedCourse->pivot->created_at->format('Y-m-d H:i:s'))
        ->toBe($assignedAt->format('Y-m-d H:i:s'));
});

test('course search columns can be configured', function (): void {
    config()->set('filament-lms.course_search_columns', ['name']);

    $relationManager = new CoursesRelationManager;
    $method = new ReflectionMethod($relationManager, 'getCourseSearchColumns');

    expect($method->invoke($relationManager))->toBe(['name']);
});

test('completion pivot rows from group access are not treated as manual assignments', function (): void {
    config([
        'filament-lms.user_groups.criteria_provider' => TestUserGroupCriteriaProvider::class,
        'filament-lms.user_groups.sync_queue' => false,
        'filament-lms.user_groups.refresh_on_user_save' => false,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Group Completer',
        'email' => 'group-completer@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create(['is_private' => true]);
    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    $step = Step::factory()->create(['lesson_id' => $lesson->id]);

    $group = UserGroup::factory()->create([
        'rules' => [
            'version' => 1,
            'sources' => [
                [
                    'source' => 'user',
                    'rules' => [
                        'rule1' => [
                            'type' => 'name',
                            'data' => [
                                'operator' => 'contains',
                                'settings' => [
                                    'text' => 'Group Completer',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $course->userGroups()->attach($group->id);
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    StepUser::query()->create([
        'user_id' => $user->id,
        'step_id' => $step->id,
        'completed_at' => now(),
    ]);
    $course->maybeSetCompletedAtForUser($user->id);

    $manager = new CoursesRelationManager;
    $manager->ownerRecord = $user;

    $assignedCourse = (new ReflectionMethod($manager, 'resolveAssignedCoursesQuery'))
        ->invoke($manager)
        ->whereKey($course->id)
        ->first();

    expect($assignedCourse)->not->toBeNull()
        ->and((new ReflectionMethod($manager, 'hasManualAssignment'))->invoke($manager, $assignedCourse))->toBeFalse()
        ->and((new ReflectionMethod($manager, 'assignmentSourceLabel'))->invoke($manager, $assignedCourse))->toBe('Group')
        ->and($course->completedByUserAt($user->id))->not->toBeNull();
});

test('explicit assignment with group membership is labeled manual plus group', function (): void {
    config([
        'filament-lms.user_groups.criteria_provider' => TestUserGroupCriteriaProvider::class,
        'filament-lms.user_groups.sync_queue' => false,
        'filament-lms.user_groups.refresh_on_user_save' => false,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Manual Group User',
        'email' => 'manual-group@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create(['is_private' => true]);
    $user->courses()->attach($course->id, ['is_explicitly_assigned' => true]);

    $group = UserGroup::factory()->create([
        'rules' => [
            'version' => 1,
            'sources' => [
                [
                    'source' => 'user',
                    'rules' => [
                        'rule1' => [
                            'type' => 'name',
                            'data' => [
                                'operator' => 'contains',
                                'settings' => [
                                    'text' => 'Manual Group',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ]);
    $course->userGroups()->attach($group->id);
    app(UserGroupMembershipSynchronizer::class)->rebuild($group->fresh());

    $manager = new CoursesRelationManager;
    $manager->ownerRecord = $user;

    $assignedCourse = (new ReflectionMethod($manager, 'resolveAssignedCoursesQuery'))
        ->invoke($manager)
        ->whereKey($course->id)
        ->first();

    expect($assignedCourse)->not->toBeNull()
        ->and((new ReflectionMethod($manager, 'hasManualAssignment'))->invoke($manager, $assignedCourse))->toBeTrue()
        ->and((new ReflectionMethod($manager, 'assignmentSourceLabel'))->invoke($manager, $assignedCourse))->toBe('Manual + Group');
});

test('explicit assignment without group membership is labeled manual', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Manual Only User',
        'email' => 'manual-only@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $user->courses()->attach($course->id, ['is_explicitly_assigned' => true]);

    $manager = new CoursesRelationManager;
    $manager->ownerRecord = $user;

    $assignedCourse = (new ReflectionMethod($manager, 'resolveAssignedCoursesQuery'))
        ->invoke($manager)
        ->whereKey($course->id)
        ->first();

    expect($assignedCourse)->not->toBeNull()
        ->and((new ReflectionMethod($manager, 'hasManualAssignment'))->invoke($manager, $assignedCourse))->toBeTrue()
        ->and((new ReflectionMethod($manager, 'assignmentSourceLabel'))->invoke($manager, $assignedCourse))->toBe('Manual');
});

test('course users manager does not treat completion-only pivot rows as detachable', function (): void {
    $user = TestUser::query()->create([
        'name' => 'Completion User',
        'email' => 'completion-user@example.com',
        'password' => bcrypt('password'),
    ]);

    $course = Course::factory()->create();
    $course->users()->attach($user->id, [
        'completed_at' => now(),
        'is_explicitly_assigned' => false,
    ]);

    $manager = new CourseUsersRelationManager;
    $assignedUser = $course->users()->whereKey($user->id)->first();

    expect($assignedUser)->not->toBeNull()
        ->and((new ReflectionMethod($manager, 'hasManualAssignment'))->invoke($manager, $assignedUser))->toBeFalse();

    $course->users()->updateExistingPivot($user->id, ['is_explicitly_assigned' => true]);
    $explicitUser = $course->users()->whereKey($user->id)->first();

    expect((new ReflectionMethod($manager, 'hasManualAssignment'))->invoke($manager, $explicitUser))->toBeTrue();
});
