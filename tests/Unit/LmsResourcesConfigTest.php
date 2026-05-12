<?php

declare(strict_types=1);

use Tapp\FilamentLms\Lms;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\CreditCategoryResource;
use Tapp\FilamentLms\Resources\LessonResource;

test('registeredResourceClasses includes package CourseResource by default', function () {
    config(['filament-lms.resources' => []]);

    expect(Lms::registeredResourceClasses())->toContain(CourseResource::class);
});

test('registeredResourceClasses respects CourseResource override from config', function () {
    config(['filament-lms.resources' => [
        'CourseResource' => LessonResource::class,
    ]]);

    $classes = Lms::registeredResourceClasses();

    expect($classes)->not->toContain(CourseResource::class);
    expect($classes)->toContain(LessonResource::class);
    expect(array_count_values($classes)[LessonResource::class] ?? 0)->toBe(1);
});

test('registeredResourceClasses omits CreditCategoryResource when credits disabled', function () {
    config([
        'filament-lms.credits_enabled' => false,
        'filament-lms.resources' => [],
    ]);

    expect(Lms::registeredResourceClasses())->not->toContain(CreditCategoryResource::class);
});

test('registeredResourceClasses includes CreditCategoryResource when credits enabled', function () {
    config([
        'filament-lms.credits_enabled' => true,
        'filament-lms.resources' => [],
    ]);

    expect(Lms::registeredResourceClasses())->toContain(CreditCategoryResource::class);
});
