<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Illuminate\View\View;
use Tapp\FilamentLms\Http\Controllers\CertificateController;
use Tapp\FilamentLms\Services\CertificatePdf\BrowsershotCertificatePdfDriver;
use Tapp\FilamentLms\Services\CertificatePdf\CertificatePdfGenerator;
use Tapp\FilamentLms\Services\CertificatePdf\CloudflareCertificatePdfDriver;

it('defaults the package config driver to null so resolution can auto-select', function () {
    expect(config('filament-lms.certificates.pdf.driver'))->toBeNull();
});

it('defaults to browsershot when the driver is unset and Cloudflare credentials are missing', function () {
    config([
        'filament-lms.certificates.pdf.driver' => null,
        'filament-lms.certificates.pdf.cloudflare.api_token' => null,
        'filament-lms.certificates.pdf.cloudflare.account_id' => null,
        'services.cloudflare.api_token' => null,
        'services.cloudflare.account_id' => null,
    ]);

    $generator = app(CertificatePdfGenerator::class);

    expect($generator->resolveDriverName())->toBe('browsershot')
        ->and($generator->driver())->toBeInstanceOf(BrowsershotCertificatePdfDriver::class);
});

it('auto-selects cloudflare when the driver is unset and Cloudflare credentials exist', function () {
    config([
        'filament-lms.certificates.pdf.driver' => null,
        'filament-lms.certificates.pdf.cloudflare.api_token' => 'test-token',
        'filament-lms.certificates.pdf.cloudflare.account_id' => 'acct-123',
    ]);

    $generator = app(CertificatePdfGenerator::class);

    expect($generator->resolveDriverName())->toBe('cloudflare')
        ->and($generator->driver())->toBeInstanceOf(CloudflareCertificatePdfDriver::class);
});

it('honors an explicit browsershot or cloudflare driver over credential auto-select', function () {
    config([
        'filament-lms.certificates.pdf.driver' => 'browsershot',
        'filament-lms.certificates.pdf.cloudflare.api_token' => 'test-token',
        'filament-lms.certificates.pdf.cloudflare.account_id' => 'acct-123',
    ]);

    $generator = app(CertificatePdfGenerator::class);

    expect($generator->resolveDriverName())->toBe('browsershot')
        ->and($generator->driver())->toBeInstanceOf(BrowsershotCertificatePdfDriver::class);

    config(['filament-lms.certificates.pdf.driver' => 'cloudflare']);

    expect($generator->resolveDriverName())->toBe('cloudflare')
        ->and($generator->driver())->toBeInstanceOf(CloudflareCertificatePdfDriver::class);
});

it('prefers CLOUDFLARE_BROWSER_RENDERING_API_TOKEN with CLOUDFLARE_API_TOKEN fallback in package config', function () {
    $config = file_get_contents(__DIR__.'/../../config/filament-lms.php');

    expect($config)->toContain("env('CLOUDFLARE_BROWSER_RENDERING_API_TOKEN', env('CLOUDFLARE_API_TOKEN'))")
        ->and($config)->toContain("env('CLOUDFLARE_ACCOUNT_ID')")
        ->and($config)->toContain("env('FILAMENT_LMS_CERTIFICATE_PDF_DRIVER')")
        ->and($config)->not->toContain("env('FILAMENT_LMS_CERTIFICATE_PDF_DRIVER', 'cloudflare')");
});

it('resolves the cloudflare and browsershot certificate pdf drivers', function () {
    $generator = app(CertificatePdfGenerator::class);

    expect($generator->driver('cloudflare'))->toBeInstanceOf(CloudflareCertificatePdfDriver::class)
        ->and($generator->driver('browsershot'))->toBeInstanceOf(BrowsershotCertificatePdfDriver::class);
});

it('rejects unknown certificate pdf drivers', function () {
    app(CertificatePdfGenerator::class)->driver('dompdf');
})->throws(InvalidArgumentException::class);

it('posts rendered html to cloudflare browser rendering and returns the pdf body', function () {
    config([
        'filament-lms.certificates.pdf.cloudflare.api_token' => 'test-token',
        'filament-lms.certificates.pdf.cloudflare.account_id' => 'acct-123',
        'filament-lms.certificates.pdf.landscape' => true,
        'filament-lms.certificates.pdf.print_background' => true,
    ]);

    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/acct-123/browser-rendering/pdf' => Http::response('%PDF-1.4 cloudflare', 200, [
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $pdf = app(CloudflareCertificatePdfDriver::class)->pdf('<html><body>Certificate</body></html>');

    expect($pdf)->toBe('%PDF-1.4 cloudflare');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return $request->url() === 'https://api.cloudflare.com/client/v4/accounts/acct-123/browser-rendering/pdf'
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ($data['html'] ?? null) === '<html><body>Certificate</body></html>'
            && ($data['pdfOptions']['landscape'] ?? false) === true
            && ($data['pdfOptions']['printBackground'] ?? false) === true;
    });
});

it('falls back to services.cloudflare credentials when package config is empty', function () {
    config([
        'filament-lms.certificates.pdf.cloudflare.api_token' => null,
        'filament-lms.certificates.pdf.cloudflare.account_id' => null,
        'services.cloudflare.api_token' => 'services-token',
        'services.cloudflare.account_id' => 'services-acct',
    ]);

    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/services-acct/browser-rendering/pdf' => Http::response('%PDF-ok', 200),
    ]);

    expect(app(CloudflareCertificatePdfDriver::class)->pdf('<p>Hi</p>'))->toBe('%PDF-ok');
});

it('throws when cloudflare credentials are missing', function () {
    config([
        'filament-lms.certificates.pdf.cloudflare.api_token' => null,
        'filament-lms.certificates.pdf.cloudflare.account_id' => null,
        'services.cloudflare.api_token' => null,
        'services.cloudflare.account_id' => null,
    ]);

    app(CloudflareCertificatePdfDriver::class)->pdf('<p>Hi</p>');
})->throws(RuntimeException::class, 'CLOUDFLARE_BROWSER_RENDERING_API_TOKEN');

it('throws a clear error when cloudflare returns a non-success response', function () {
    config([
        'filament-lms.certificates.pdf.cloudflare.api_token' => 'test-token',
        'filament-lms.certificates.pdf.cloudflare.account_id' => 'acct-123',
    ]);

    Http::fake([
        'https://api.cloudflare.com/client/v4/accounts/acct-123/browser-rendering/pdf' => Http::response(['errors' => ['nope']], 403),
    ]);

    app(CloudflareCertificatePdfDriver::class)->pdf('<p>Hi</p>');
})->throws(RuntimeException::class, 'Cloudflare Browser Rendering failed');

it('injects a base href into certificate html for pdf rendering', function () {
    config(['app.url' => 'https://portal.example.test']);

    $view = Mockery::mock(View::class);
    $view->shouldReceive('render')->once()->andReturn('<html><head><title>Cert</title></head><body>OK</body></html>');

    $html = (new CertificateController)->htmlForPdf($view);

    expect($html)->toContain('<base href="https://portal.example.test/">')
        ->and($html)->toContain('<head><base href="https://portal.example.test/">');
});

it('configures browsershot html driver with landscape and no-sandbox', function () {
    config([
        'filament-lms.certificates.pdf.landscape' => true,
        'filament-lms.certificates.pdf.print_background' => true,
        'filament-lms.certificates.pdf.browsershot.wait_until_network_idle' => true,
    ]);

    $driver = new BrowsershotCertificatePdfDriver;
    $command = $driver->browsershot('<html><body>Certificate</body></html>')->createPdfCommand();

    expect($command['options']['args'] ?? [])
        ->toContain('--no-sandbox')
        ->and($command['options']['landscape'] ?? false)->toBeTrue()
        ->and($command['options']['printBackground'] ?? false)->toBeTrue()
        ->and($command['options']['waitUntil'] ?? null)->toBe('networkidle0');
});
