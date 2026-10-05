<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CertificatePdf;

use InvalidArgumentException;
use Tapp\FilamentLms\Contracts\CertificatePdfDriver;

final class CertificatePdfGenerator
{
    public function driver(?string $name = null): CertificatePdfDriver
    {
        $name ??= (string) config('filament-lms.certificates.pdf.driver', 'cloudflare');

        return match ($name) {
            'cloudflare' => app(CloudflareCertificatePdfDriver::class),
            'browsershot' => app(BrowsershotCertificatePdfDriver::class),
            default => throw new InvalidArgumentException(
                "Unsupported certificate PDF driver [{$name}]. Use cloudflare or browsershot."
            ),
        };
    }

    public function pdf(string $html, ?string $driver = null): string
    {
        return $this->driver($driver)->pdf($html);
    }
}
