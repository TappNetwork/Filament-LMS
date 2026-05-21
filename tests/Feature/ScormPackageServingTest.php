<?php

declare(strict_types=1);

use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Services\CommonCartridge\CommonCartridgeImportService;
use Tapp\FilamentLms\Services\CommonCartridge\ManifestParser;
use Tapp\FilamentLms\Services\CommonCartridge\ScormPackageStorage;
use Tapp\FilamentLms\Tests\TestUser;

test('authenticated user can load scorm package entry file', function () {
    config([
        'filament-lms.common_cartridge_import.retain_extracted_packages' => true,
        'filament-lms.user_model' => TestUser::class,
    ]);

    $user = TestUser::query()->create([
        'name' => 'Learner',
        'first_name' => 'Learner',
        'last_name' => 'User',
        'email' => 'learner@example.com',
        'password' => bcrypt('password'),
    ]);

    $fixturePath = realpath(__DIR__.'/../fixtures/articulate-rise');
    $service = new CommonCartridgeImportService(new ManifestParser, new ScormPackageStorage);
    $result = $service->import($fixturePath);
    $course = $result['course'];

    $this->actingAs($user);

    $document = Document::query()->first();
    expect($document)->not->toBeNull();

    $response = $this->get(route('filament-lms.scorm-package.show', [
        'document' => $document->id,
        'entry' => 'scormcontent/index.html',
    ]));

    $response->assertSuccessful();
});
