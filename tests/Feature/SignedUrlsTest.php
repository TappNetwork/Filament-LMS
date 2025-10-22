<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Course;

test('course image url fallback to placeholder when no media', function () {
    $course = Course::factory()->create();

    $imageUrl = $course->image_url;

    expect($imageUrl)->toBe('https://picsum.photos/200');
});

test('course image url uses regular url when signed urls disabled', function () {
    // Disable signed URLs
    config(['filament-lms.media.use_signed_urls' => false]);

    $course = Course::factory()->create();

    $imageUrl = $course->image_url;

    // Should return placeholder since no media is attached
    expect($imageUrl)->toBe('https://picsum.photos/200');
});

test('has media url trait returns null when no media', function () {
    $course = Course::factory()->create();

    $mediaUrl = $course->getMediaUrl('courses');

    expect($mediaUrl)->toBeNull();
});

test('configuration is properly set', function () {
    config([
        'filament-lms.media.use_signed_urls' => true,
        'filament-lms.media.signed_url_expiration' => 60,
    ]);

    expect(config('filament-lms.media.use_signed_urls'))->toBeTrue();
    expect(config('filament-lms.media.signed_url_expiration'))->toBe(60);
});
