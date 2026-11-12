<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Tapp\FilamentLms\Lms;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Pages\Dashboard;

test('course catalog defaults to alphabetical name', function () {
    courseSortCatalogCourse('Zebra');
    courseSortCatalogCourse('Alpha');
    courseSortCatalogCourse('Mango');

    expect(Lms::make()->getCourseSort())->toBe('name')
        ->and(Lms::make()->getCourseSortDirection())->toBe('asc')
        ->and(Lms::applyCourseSort(Course::query())->pluck('name')->all())->toBe(['Alpha', 'Mango', 'Zebra'])
        ->and((new Dashboard)->courses()->pluck('name')->all())->toBe(['Alpha', 'Mango', 'Zebra']);
});

test('courseSort chooses the catalog and can restore newest first', function () {
    courseSortCatalogCourse('Alpha', now()->subDays(2));
    courseSortCatalogCourse('Zebra', now());

    Lms::make()->courseSort('created_at', 'desc');

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())->toBe(['Zebra', 'Alpha'])
        ->and((new Dashboard)->courses()->pluck('name')->all())->toBe(['Zebra', 'Alpha']);
});

test('a closure can replace the course sort', function () {
    courseSortCatalogCourse('Alpha');
    courseSortCatalogCourse('Zebra');

    Lms::make()->courseSort(fn (Builder $query): Builder => $query->orderBy('name', 'desc'));

    expect(Lms::applyCourseSort(Course::query())->pluck('name')->all())->toBe(['Zebra', 'Alpha']);
});

test('an empty course sort column is rejected', function () {
    Lms::make()->courseSort(' ');
})->throws(InvalidArgumentException::class);

function courseSortCatalogCourse(string $name, mixed $createdAt = null): Course
{
    $course = Course::factory()->withoutCertificateTemplate()->create([
        'name' => $name,
        'slug' => str($name)->slug()->toString(),
        'external_id' => str($name)->slug('_')->toString(),
    ]);

    if ($createdAt !== null) {
        $course->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->save();
    }

    $lesson = Lesson::factory()->create(['course_id' => $course->id]);
    Step::factory()->withoutMaterial()->create(['lesson_id' => $lesson->id]);

    return $course->refresh();
}
