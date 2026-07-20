<?php

declare(strict_types=1);

use Tapp\FilamentLms\Http\Controllers\CertificateController;

it('configures browsershot with no-sandbox for production chromium hosts', function () {
    $controller = new CertificateController;
    $command = $controller->browsershot('https://example.com/lms/certificates/1/1')->createPdfCommand();

    expect($command['options']['args'] ?? [])
        ->toContain('--no-sandbox')
        ->and($command['options']['landscape'] ?? false)->toBeTrue()
        ->and($command['options']['printBackground'] ?? false)->toBeTrue()
        ->and($command['options']['waitUntil'] ?? null)->toBe('networkidle0');
});
