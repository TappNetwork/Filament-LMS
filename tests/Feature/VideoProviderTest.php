<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Models\Video;

test('video provider returns youtube for youtube.com embed URL', function () {
    $video = Video::create([
        'name' => 'Test',
        'url' => 'https://www.youtube.com/embed/abc123',
    ]);
    expect($video->provider)->toBe('youtube');
});

test('video provider returns youtube for youtube-nocookie.com embed URL', function () {
    $video = Video::create([
        'name' => 'Test',
        'url' => 'https://www.youtube-nocookie.com/embed/abc123',
    ]);
    expect($video->provider)->toBe('youtube');
});

test('video provider returns youtube for youtu.be URL', function () {
    $video = Video::create([
        'name' => 'Test',
        'url' => 'https://youtu.be/abc123',
    ]);
    expect($video->provider)->toBe('youtube');
});

test('video provider returns vimeo for vimeo.com URL', function () {
    $video = Video::create([
        'name' => 'Test',
        'url' => 'https://vimeo.com/123456',
    ]);
    expect($video->provider)->toBe('vimeo');
});

test('video provider returns external for unknown URL', function () {
    $video = Video::create([
        'name' => 'Test',
        'url' => 'https://example.com/video',
    ]);
    expect($video->provider)->toBe('external');
});
