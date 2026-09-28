<?php

declare(strict_types=1);

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\RelationManagers\CourseUserGroupsRelationManager;
use Tapp\FilamentLms\Tests\Support\TestUserGroupCriteriaProvider;

it('is configured for the userGroups relationship', function (): void {
    $reflection = new ReflectionClass(CourseUserGroupsRelationManager::class);

    expect(CourseUserGroupsRelationManager::getRelationshipName())->toBe('userGroups')
        ->and($reflection->getStaticPropertyValue('title'))->toBe('Assigned User Groups');
});

it('keeps criteria filters visible by default with a how-to description', function (): void {
    $source = file_get_contents(
        (new ReflectionClass(CourseUserGroupsRelationManager::class))->getFileName()
    );

    expect($source)
        ->toContain('FiltersLayout::AboveContent')
        ->not->toContain('FiltersLayout::AboveContentCollapsible')
        ->toContain('course-user-groups-description')
        ->toContain('updatedActiveGroupId');
});

it('is hidden when no criteria provider is configured', function (): void {
    config(['filament-lms.user_groups.criteria_provider' => null]);

    $course = Course::factory()->create();

    expect(CourseUserGroupsRelationManager::canViewForRecord($course, 'edit'))->toBeFalse();
});

it('is visible when a criteria provider is configured', function (): void {
    config(['filament-lms.user_groups.criteria_provider' => TestUserGroupCriteriaProvider::class]);

    $course = Course::factory()->create();

    expect(CourseUserGroupsRelationManager::canViewForRecord($course, 'edit'))->toBeTrue();
});

it('marks the first attached group as the course default and can switch defaults', function (): void {
    $course = Course::factory()->create();
    $first = UserGroup::factory()->create(['name' => 'First Group']);
    $second = UserGroup::factory()->create(['name' => 'Second Group']);

    $course->userGroups()->attach($first->id, ['is_default' => true]);
    $course->userGroups()->attach($second->id, ['is_default' => false]);

    expect($course->defaultUserGroup()?->is($first))->toBeTrue();

    $course->setDefaultUserGroup($second->id);
    $course->refresh();

    expect($course->defaultUserGroup()?->is($second))->toBeTrue()
        ->and($course->userGroups()->wherePivot('is_default', true)->count())->toBe(1);
});
