<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CertificatePdf;

use InvalidArgumentException;
use Tapp\FilamentLms\Contracts\CertificatePdfDriver;

final class CertificatePdfGenerator
{
    public function driver(?string $name = null): CertificatePdfDriver
    {
        $name ??= $this->resolveDriverName();

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

    /**
     * Resolve the active driver name.
     *
     * Explicit FILAMENT_LMS_CERTIFICATE_PDF_DRIVER (or config value) always wins.
     * When unset/null/empty: use cloudflare only if Cloudflare credentials are present;
     * otherwise browsershot (Forge / existing hosts need no env changes).
     */
    public function resolveDriverName(): string
    {
        $configured = config('filament-lms.certificates.pdf.driver');

        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        return $this->hasCloudflareCredentials() ? 'cloudflare' : 'browsershot';
    }

    private function hasCloudflareCredentials(): bool
    {
        $token = config('filament-lms.certificates.pdf.cloudflare.api_token')
            ?? config('services.cloudflare.api_token');
        $accountId = config('filament-lms.certificates.pdf.cloudflare.account_id')
            ?? config('services.cloudflare.account_id');

        $token = is_string($token) ? trim($token) : '';
        $accountId = is_string($accountId) ? trim($accountId) : '';

        return $token !== '' && $accountId !== '';
    }
}
