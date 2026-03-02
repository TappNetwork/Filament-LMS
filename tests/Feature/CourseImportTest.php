<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentLms\Imports\CourseStepsImport;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Tests\TestCase;

beforeEach(function () {
    config(['filament-lms.user_model' => \Tapp\FilamentLms\Tests\TestUser::class]);
});

test('course steps import format a creates course lessons steps and videos', function () {
    $path = __DIR__.'/../fixtures/course-import-format-a.csv';

    Excel::import(new CourseStepsImport('Imported Course'), $path);

    $course = Course::where('name', 'Imported Course')->first();
    expect($course)->not->toBeNull();
    expect($course->slug)->toBe('imported-course');

    $lessons = $course->lessons()->orderBy('order')->get();
    expect($lessons)->toHaveCount(2);
    expect($lessons->pluck('name')->toArray())->toBe(['Background', 'Other Lesson']);

    $steps = $course->steps()->orderBy('lms_lessons.order')->orderBy('lms_steps.order')->get();
    expect($steps)->toHaveCount(3);

    $videos = Video::all();
    expect($videos)->toHaveCount(3);

    $firstStep = $steps->first();
    expect($firstStep->material_type)->toBe('video');
    expect($firstStep->material_id)->toBe($videos->first()->id);
});

test('course steps import format a falls back to course name when lesson name is empty', function () {
    $path = __DIR__.'/../fixtures/course-import-format-a-empty-lesson-name.csv';

    Excel::import(new CourseStepsImport('My Course'), $path);

    $course = Course::where('name', 'My Course')->first();
    expect($course)->not->toBeNull();

    $lessons = $course->lessons()->orderBy('order')->get();
    expect($lessons)->toHaveCount(1);
    expect($lessons->first()->name)->toBe('My Course');
    expect($lessons->first()->slug)->toBe('my-course');
});

test('course steps import format b creates course with steps and text', function () {
    $path = __DIR__.'/../fixtures/course-import-format-b.csv';

    Excel::import(new CourseStepsImport('Format B Course'), $path);

    $course = Course::where('name', 'Format B Course')->first();
    expect($course)->not->toBeNull();

    $steps = $course->steps()->orderBy('order')->get();
    expect($steps)->toHaveCount(2);
    expect($steps->first()->name)->toBe('Step One');
    expect($steps->first()->text)->toBe('Some script text here');
    expect($steps->get(1)->text)->toBe('More text');
});
