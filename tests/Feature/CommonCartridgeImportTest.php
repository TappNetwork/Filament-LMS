<?php

declare(strict_types=1);

use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;
use Tapp\FilamentLms\Services\CommonCartridge\ManifestParser;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function () {
    config(['filament-lms.user_model' => TestUser::class]);
});

test('manifest parser extracts course title and single lesson with one step from fixture', function () {
    $fixturePath = __DIR__.'/../fixtures/common-cartridge';
    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->courseTitle)->toBe('Child Outcomes Summary');
    expect($manifest->lessons)->toHaveCount(1);
    expect($manifest->lessons[0]->title)->toBe('Content');
    expect($manifest->lessons[0]->steps[0]->title)->toBe('Child Outcomes Summary');
    expect($manifest->lessons[0]->steps)->toHaveCount(1);
    expect($manifest->lessons[0]->steps[0]->resourceIdentifier)->toBe('__6c3M623PUXY_course_id_RES');
    expect($manifest->resources)->toHaveCount(1);
    $resource = $manifest->resources['__6c3M623PUXY_course_id_RES'];
    expect($resource->type)->toBe('webcontent');
    expect($resource->href)->toBe('index_lms.html');
});

test('import service creates course lesson step and document from fixture', function () {
    $fixturePath = realpath(__DIR__.'/../fixtures/common-cartridge');
    expect($fixturePath)->not->toBeFalse();
    if (! is_file($fixturePath.'/index_lms.html')) {
        test()->markTestSkipped('Fixture must contain index_lms.html');
    }
    $parser = new ManifestParser;
    $service = new CommonCartridgeImportService($parser);

    $result = $service->import($fixturePath);

    expect($result['course'])->toBeInstanceOf(Course::class);
    expect($result['course']->name)->toBe('Child Outcomes Summary');
    expect($result['lessons_created'])->toBe(1);
    expect($result['steps_created'])->toBe(1);

    $course = $result['course'];
    $course->load('lessons.steps.material');
    expect($course->lessons)->toHaveCount(1);
    $lesson = $course->lessons->first();
    expect($lesson->steps)->toHaveCount(1);
    $step = $lesson->steps->first();
    expect($step->material_type)->toBe('document');
    expect($step->material_id)->not->toBeNull();
    $document = $step->material;
    expect($document)->toBeInstanceOf(Document::class);
    expect($document->getFirstMedia())->not->toBeNull();
});

test('parser throws when imsmanifest.xml is missing', function () {
    $parser = new ManifestParser;
    $parser->parse('/nonexistent/path');
})->throws(InvalidArgumentException::class);

test('manifest parser uses Articulate frame.xml when present for lessons and steps', function () {
    $fixturePath = __DIR__.'/../fixtures/common-cartridge-with-articulate';
    $parser = new ManifestParser;
    $manifest = $parser->parse($fixturePath);

    expect($manifest->courseTitle)->toBe('Child Outcomes Summary');
    expect($manifest->lessons)->toHaveCount(2);
    expect($manifest->lessons[0]->title)->toBe('Home');
    expect($manifest->lessons[0]->steps)->toHaveCount(3);
    expect($manifest->lessons[0]->steps[0]->title)->toBe('Home');
    expect($manifest->lessons[0]->steps[1]->title)->toBe('Child Outcomes Summary (COS) Process Online Module');
    expect($manifest->lessons[0]->steps[2]->title)->toBe('What To Expect');
    expect($manifest->lessons[1]->title)->toBe('Session 1');
    expect($manifest->lessons[1]->steps)->toHaveCount(2);
    expect($manifest->lessons[1]->steps[0]->title)->toBe("So What's This All About?");
    expect($manifest->lessons[1]->steps[1]->title)->toBe('Set-Up');
});
