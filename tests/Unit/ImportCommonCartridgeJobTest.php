<?php

declare(strict_types=1);

use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;

test('extractZip returns nested html5 package root and exposes temp root for cleanup', function () {
    $zipBasePath = tempnam(sys_get_temp_dir(), 'cc-import-');
    $zipPath = $zipBasePath.'.zip';
    @unlink($zipBasePath);

    $zip = new ZipArchive;
    expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('wrapped/index.html', '<html><body>Storyline</body></html>');
    $zip->addFromString('wrapped/story_content/frame.xml', '<frame />');
    $zip->close();

    $job = new ImportCommonCartridgeJob($zipPath, 1);
    $method = new ReflectionMethod($job, 'extractZip');
    $method->setAccessible(true);

    $tempRootPath = null;
    $packageRoot = $method->invokeArgs($job, [$zipPath, &$tempRootPath]);

    expect($tempRootPath)->not->toBeNull();
    expect($packageRoot)->toBe($tempRootPath.'/wrapped');
    expect(is_dir($tempRootPath))->toBeTrue();
    expect(is_file($packageRoot.'/story_content/frame.xml'))->toBeTrue();

    $deleteDirectory = new ReflectionMethod($job, 'deleteDirectory');
    $deleteDirectory->setAccessible(true);
    $deleteDirectory->invoke($job, $tempRootPath);

    expect(is_dir($tempRootPath))->toBeFalse();

    @unlink($zipPath);
});
