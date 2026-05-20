<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;

beforeEach(function () {
    $this->course = Course::query()->create([
        'name' => 'Test Course',
        'slug' => 'test-course',
        'external_id' => 'test_course',
        'award' => 'default',
    ]);
    $this->lesson = Lesson::query()->create([
        'course_id' => $this->course->id,
        'order' => 0,
        'name' => 'Test Lesson',
        'slug' => 'test-lesson',
    ]);
});

test('getRenderedText returns empty string when text is null', function () {
    $step = Step::query()->create([
        'lesson_id' => $this->lesson->id,
        'order' => 0,
        'name' => 'Step',
        'slug' => 'step',
        'text' => null,
    ]);

    expect($step->getRenderedText())->toBe('');
});

test('getRenderedText returns empty string when text is blank', function () {
    $step = Step::query()->create([
        'lesson_id' => $this->lesson->id,
        'order' => 0,
        'name' => 'Step',
        'slug' => 'step',
        'text' => '   ',
    ]);

    expect($step->getRenderedText())->toBe('');
});

test('getRenderedText renders markdown when text does not start with angle bracket', function () {
    $step = Step::query()->create([
        'lesson_id' => $this->lesson->id,
        'order' => 0,
        'name' => 'Step',
        'slug' => 'step',
        'text' => 'Hello **world**',
    ]);

    expect($step->getRenderedText())->toContain('<strong>');
    expect($step->getRenderedText())->toContain('world');
});

test('getRenderedText sanitizes and returns HTML when text starts with angle bracket', function () {
    $step = Step::query()->create([
        'lesson_id' => $this->lesson->id,
        'order' => 0,
        'name' => 'Step',
        'slug' => 'step',
        'text' => '<p>HTML content</p>',
    ]);

    expect($step->getRenderedText())->toContain('<p>');
    expect($step->getRenderedText())->toContain('HTML content');
});

test('getRenderedText strips script tags from HTML', function () {
    $step = Step::query()->create([
        'lesson_id' => $this->lesson->id,
        'order' => 0,
        'name' => 'Step',
        'slug' => 'step',
        'text' => '<p>Safe</p><script>alert(1)</script>',
    ]);

    expect($step->getRenderedText())->not->toContain('<script>');
    expect($step->getRenderedText())->toContain('Safe');
});
