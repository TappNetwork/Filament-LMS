<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CertificatePdf;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tapp\FilamentLms\Contracts\CertificatePdfDriver;

final class CloudflareCertificatePdfDriver implements CertificatePdfDriver
{
    public function pdf(string $html): string
    {
        $token = $this->apiToken();
        $accountId = $this->accountId();

        if ($token === '' || $accountId === '') {
            throw new RuntimeException(
                'Certificate PDF driver [cloudflare] requires CLOUDFLARE_API_TOKEN and CLOUDFLARE_ACCOUNT_ID. '.
                'See the Filament LMS certificate PDF documentation.'
            );
        }

        $endpoint = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/browser-rendering/pdf',
            $accountId
        );

        try {
            $response = Http::withToken($token)
                ->accept('application/pdf')
                ->timeout((int) config('filament-lms.certificates.pdf.cloudflare.timeout', 60))
                ->post($endpoint, [
                    'html' => $html,
                    'pdfOptions' => [
                        'landscape' => (bool) config('filament-lms.certificates.pdf.landscape', true),
                        'printBackground' => (bool) config('filament-lms.certificates.pdf.print_background', true),
                    ],
                ])
                ->throw();
        } catch (RequestException $exception) {
            throw new RuntimeException(
                'Cloudflare Browser Rendering failed to generate the certificate PDF: '.$exception->getMessage(),
                previous: $exception
            );
        }

        $body = $response->body();

        if ($body === '') {
            throw new RuntimeException('Cloudflare Browser Rendering returned an empty PDF response.');
        }

        return $body;
    }

    private function apiToken(): string
    {
        $token = config('filament-lms.certificates.pdf.cloudflare.api_token')
            ?? config('services.cloudflare.api_token');

        return is_string($token) ? trim($token) : '';
    }

    private function accountId(): string
    {
        $accountId = config('filament-lms.certificates.pdf.cloudflare.account_id')
            ?? config('services.cloudflare.account_id');

        return is_string($accountId) ? trim($accountId) : '';
    }
}
