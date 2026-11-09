<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp;

use finfo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

class RemoteMediaFetcher
{
    public const DOCUMENT_MIME_TYPES = [
        'application/pdf' => 'pdf',
    ];

    public const IMAGE_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Downloads to a temp file the caller must move or delete.
     *
     * @param  array<string, string>  $allowedMimeTypes  MIME type => file extension
     * @return array{path: string, mime_type: string, extension: string}
     *
     * @throws ValidationException
     */
    public function download(string $url, array $allowedMimeTypes, string $attribute): array
    {
        $pin = $this->assertPublicUrl($url, ['https'], $attribute);
        $maxBytes = $this->maxBytes();

        $tooLarge = function (int $bytes) use ($maxBytes): void {
            if ($bytes > $maxBytes) {
                throw new RuntimeException('Remote file exceeds the size limit.');
            }
        };

        try {
            $response = Http::timeout(30)
                ->connectTimeout(10)
                ->withoutRedirecting()
                ->withOptions(array_filter([
                    'on_headers' => fn (ResponseInterface $response) => $tooLarge((int) $response->getHeaderLine('Content-Length')),
                    'progress' => fn (int $downloadTotal, int $downloaded) => $tooLarge(max($downloadTotal, $downloaded)),
                    'curl' => $pin === null ? null : [CURLOPT_RESOLVE => [$pin]],
                ]))
                ->get($url);
        } catch (Throwable) {
            $this->fail($attribute, 'The file could not be downloaded. Check the URL is public, uses https, and is under '.$this->maxKilobytes().' KB.');
        }

        if (! $response->successful()) {
            $this->fail($attribute, 'The file URL returned HTTP '.$response->status().'. Redirects are not followed; use the final URL.');
        }

        $headerType = strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]));

        if (! array_key_exists($headerType, $allowedMimeTypes)) {
            $this->fail($attribute, 'The file content type must be one of: '.implode(', ', array_keys($allowedMimeTypes)).'.');
        }

        $body = $response->body();

        if (strlen($body) > $maxBytes) {
            $this->fail($attribute, 'The file must not be larger than '.$this->maxKilobytes().' KB.');
        }

        $path = sys_get_temp_dir().'/lms-mcp-'.Str::uuid();
        file_put_contents($path, $body);

        $sniffedType = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($sniffedType) || ! array_key_exists($sniffedType, $allowedMimeTypes)) {
            @unlink($path);
            $this->fail($attribute, 'The file contents do not match an allowed type: '.implode(', ', array_keys($allowedMimeTypes)).'.');
        }

        return [
            'path' => $path,
            'mime_type' => $sniffedType,
            'extension' => $allowedMimeTypes[$sniffedType],
        ];
    }

    /**
     * Returns a CURLOPT_RESOLVE entry that pins the request to the checked IP, or null for IP literals.
     *
     * @param  list<string>  $schemes
     *
     * @throws ValidationException
     */
    public function assertPublicUrl(string $url, array $schemes, string $attribute): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            $this->fail($attribute, 'The URL is invalid.');
        }

        $scheme = strtolower($parts['scheme']);

        if (! in_array($scheme, $schemes, true)) {
            $this->fail($attribute, 'The URL must use '.implode(' or ', $schemes).'.');
        }

        $host = trim($parts['host'], '[]');
        $isIpLiteral = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $ips = $isIpLiteral ? [$host] : $this->resolveHost($host);

        if ($ips === []) {
            $this->fail($attribute, 'The URL host could not be resolved.');
        }

        foreach ($ips as $ip) {
            // 64:ff9b::/96 is the NAT64 well-known prefix. PHP treats it as global,
            // but the embedded IPv4 can be a private address.
            if ($this->isNat64($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                $this->fail($attribute, 'The URL must point to a public host.');
            }
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        $allowedPorts = in_array('http', $schemes, true) ? [443, 80] : [443];

        if (! in_array($port, $allowedPorts, true)) {
            $this->fail($attribute, 'The URL must use port '.implode(' or ', $allowedPorts).'.');
        }

        if ($isIpLiteral) {
            return null;
        }

        return $host.':'.$port.':'.$this->pinAddress($ips);
    }

    /**
     * @param  list<string>  $ips
     */
    protected function pinAddress(array $ips): string
    {
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $ip;
            }
        }

        $ip = $ips[0];

        return str_contains($ip, ':') ? '['.$ip.']' : $ip;
    }

    protected function isNat64(string $ip): bool
    {
        $packed = @inet_pton($ip);
        $prefix = inet_pton('64:ff9b::');

        if ($packed === false || $prefix === false || strlen($packed) !== 16) {
            return false;
        }

        return substr($packed, 0, 12) === substr($prefix, 0, 12);
    }

    /**
     * @return list<string>
     */
    protected function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (! is_array($records)) {
            return [];
        }

        $ips = [];

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;

            if (is_string($ip)) {
                $ips[] = $ip;
            }
        }

        return $ips;
    }

    protected function maxKilobytes(): int
    {
        return max(1, (int) config('filament-lms.mcp.remote_media_max_kb', 20480));
    }

    protected function maxBytes(): int
    {
        return $this->maxKilobytes() * 1024;
    }

    /**
     * @throws ValidationException
     */
    protected function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
