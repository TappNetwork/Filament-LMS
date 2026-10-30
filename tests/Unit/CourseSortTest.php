<?php

declare(strict_types=1);

use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Lms;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Support\CourseListingSort;
use Tapp\FilamentLms\Tests\TestUser;

/**
 * Created-at is independent of name so each order is a different sequence:
 * newest: Mango, Zebra, Alpha
 * oldest: Alpha, Zebra, Mango
 * name asc: Alpha, Mango, Zebra
 * name desc: Zebra, Mango, Alpha
 */
test('course catalog keeps newest first until a sort is configured', function () {
    seedCourseSortCatalog();

    $user = courseSortUser('default-auth@example.com');
    Auth::login($user);

    expect(Lms::make()->getCourseSort())->toBeNull()
        ->and(Lms::make()->getCourseSortDirection())->toBe('desc')
        ->and(Lms::courseSortConfiguration())->toBeNull()
        ->and(Lms::applyCourseSort(Course::query())->getQuery()->orders)
        ->toBe(Course::query()->latest()->getQuery()->orders)
        ->and(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Mango', 'Zebra', 'Alpha'])
        ->and((new Dashboard)->courses()->pluck('name')->all())
        ->toBe(['Mango', 'Zebra', 'Alpha']);

    Auth::logout();

    expect((new Dashboard)->courses()->pluck('name')->all())
        ->toBe(['Mango', 'Zebra', 'Alpha']);

    $table = courseSortAdminTable();

    expect($table->getDefaultSortColumn())->toBeNull()
        ->and($table->getDefaultSortQuery())->toBeNull()
        ->and($table->hasDefaultKeySort())->toBeTrue();
});

test('courseSort name orders the catalog and the admin course table', function (string $direction, array $names) {
    seedCourseSortCatalog();

    Lms::make()->courseSort('name', $direction);

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())->toBe($names)
        ->and((new Dashboard)->courses()->pluck('name')->all())->toBe($names);

    Auth::login(courseSortUser('name-'.$direction.'@example.com'));

    expect((new Dashboard)->courses()->pluck('name')->all())->toBe($names);

    $table = courseSortAdminTable();

    expect($table->getDefaultSortColumn())->toBe('name')
        ->and($table->getDefaultSortDirection())->toBe($direction)
        ->and(Course::query()->orderBy($table->getDefaultSortColumn(), $table->getDefaultSortDirection())->pluck('name')->all())
        ->toBe($names);
})->with([
    'ascending' => ['asc', ['Alpha', 'Mango', 'Zebra']],
    'descending' => ['desc', ['Zebra', 'Mango', 'Alpha']],
]);

test('courseSort created_at orders the catalog and the admin course table', function (string $direction, array $names) {
    seedCourseSortCatalog();

    Lms::make()->courseSort('created_at', $direction);

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())->toBe($names)
        ->and((new Dashboard)->courses()->pluck('name')->all())->toBe($names);

    $table = courseSortAdminTable();

    expect($table->getDefaultSortColumn())->toBe('created_at')
        ->and($table->getDefaultSortDirection())->toBe($direction)
        ->and(Course::query()->orderBy('created_at', $direction)->pluck('name')->all())
        ->toBe($names);
})->with([
    'oldest first' => ['asc', ['Alpha', 'Zebra', 'Mango']],
    'newest first' => ['desc', ['Mango', 'Zebra', 'Alpha']],
]);

test('a closure can replace the course sort', function () {
    seedCourseSortCatalog();

    Lms::make()->courseSort(
        fn (Builder $query, string $direction): Builder => $query->orderBy('name', $direction),
        'desc',
    );

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Zebra', 'Mango', 'Alpha'])
        ->and((new Dashboard)->courses()->pluck('name')->all())
        ->toBe(['Zebra', 'Mango', 'Alpha']);

    $table = courseSortAdminTable();
    $sort = $table->getDefaultSortQuery();

    expect($sort)->toBeInstanceOf(Closure::class)
        ->and($table->getDefaultSortDirection())->toBe('desc')
        ->and($sort(Course::query(), 'desc')->pluck('name')->all())
        ->toBe(['Zebra', 'Mango', 'Alpha'])
        ->and($sort(Course::query(), 'asc')->pluck('name')->all())
        ->toBe(['Alpha', 'Mango', 'Zebra']);
});

test('a direction other than desc is treated as ascending', function () {
    seedCourseSortCatalog();

    $plugin = Lms::make()->courseSort('name', 'DESC');

    expect($plugin->getCourseSortDirection())->toBe('asc')
        ->and(Lms::courseSortConfiguration()[1])->toBe('asc')
        ->and(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Alpha', 'Mango', 'Zebra']);
});

test('an empty course sort column is rejected', function (string $column) {
    expect(fn () => Lms::make()->courseSort($column))
        ->toThrow(InvalidArgumentException::class);

    expect(app()->bound(CourseListingSort::class))->toBeFalse()
        ->and(Lms::courseSortConfiguration())->toBeNull();
})->with(['', ' ', '   ']);

test('the current panel plugin sort wins over a different container binding', function () {
    seedCourseSortCatalog();

    $plugin = Lms::make()->courseSort('name', 'asc');
    app()->instance(CourseListingSort::class, new CourseListingSort('created_at', 'asc'));
    courseSortUsePanelPlugin($plugin);

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Alpha', 'Mango', 'Zebra']);

    $table = courseSortAdminTable();

    expect($table->getDefaultSortColumn())->toBe('name')
        ->and($table->getDefaultSortDirection())->toBe('asc');
});

test('an unconfigured panel plugin does not change the default or hide a container sort', function () {
    seedCourseSortCatalog();

    courseSortUsePanelPlugin(Lms::make());

    expect(Lms::courseSortConfiguration())->toBeNull()
        ->and(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Mango', 'Zebra', 'Alpha'])
        ->and(courseSortAdminTable()->getDefaultSortColumn())->toBeNull();

    Lms::make()->courseSort('name', 'desc');

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())
        ->toBe(['Zebra', 'Mango', 'Alpha'])
        ->and(courseSortAdminTable()->getDefaultSortColumn())->toBe('name')
        ->and(courseSortAdminTable()->getDefaultSortDirection())->toBe('desc');
});

function seedCourseSortCatalog(): void
{
    courseSortCatalogCourse('Alpha', now()->subDays(2));
    courseSortCatalogCourse('Mango', now());
    courseSortCatalogCourse('Zebra', now()->subDay());
}

function courseSortCatalogCourse(string $name, mixed $createdAt): Course
{
    $course = Course::factory()->create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'external_id' => str($name)->slug('_')->toString(),
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->withoutMaterial()->create(['lesson_id' => $lesson->id]);

    return $course->refresh();
}

function courseSortUser(string $email): TestUser
{
    return TestUser::create([
        'name' => 'Course Sort User',
        'email' => $email,
        'password' => bcrypt('password'),
    ]);
}

function courseSortAdminTable(): Table
{
    return CourseResource::table(Table::make(Mockery::mock(HasTable::class)));
}

function courseSortUsePanelPlugin(Lms $plugin): void
{
    $panel = Panel::make()->id('course-sort');
    $plugins = new ReflectionProperty($panel, 'plugins');
    $plugins->setValue($panel, ['lms' => $plugin]);

    Filament::setCurrentPanel($panel);
}
