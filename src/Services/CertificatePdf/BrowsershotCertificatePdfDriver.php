<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CertificatePdf;

use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Tapp\FilamentLms\Contracts\CertificatePdfDriver;

/**
 * Optional local/dev driver. Requires Node, Puppeteer, and spatie/browsershot.
 * Do not use this as the production driver on Laravel Cloud.
 */
final class BrowsershotCertificatePdfDriver implements CertificatePdfDriver
{
    public function pdf(string $html): string
    {
        if (! class_exists(Browsershot::class)) {
            throw new RuntimeException(
                'Certificate PDF driver [browsershot] requires spatie/browsershot. '.
                'Install it or set FILAMENT_LMS_CERTIFICATE_PDF_DRIVER=cloudflare.'
            );
        }

        return $this->browsershot($html)->pdf();
    }

    public function browsershot(string $html): Browsershot
    {
        $browsershot = Browsershot::html($html)
            ->noSandbox()
            ->landscape((bool) config('filament-lms.certificates.pdf.landscape', true));

        if ((bool) config('filament-lms.certificates.pdf.print_background', true)) {
            $browsershot->showBackground();
        }

        if ((bool) config('filament-lms.certificates.pdf.browsershot.wait_until_network_idle', true)) {
            $browsershot->waitUntilNetworkIdle();
        }

        return $browsershot;
    }
}
