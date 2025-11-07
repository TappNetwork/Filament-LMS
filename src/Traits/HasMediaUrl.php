<?php

namespace Tapp\FilamentLms\Traits;

use Illuminate\Support\Facades\Log;

trait HasMediaUrl
{
    /**
     * Get a media URL with support for signed URLs for private storage.
     * Automatically falls back to regular URLs for local storage or misconfigured cloud storage.
     */
    public function getMediaUrl(string $collection, string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        // Check if signed URLs are enabled in config
        if (config('filament-lms.media.use_signed_urls', false)) {
            // Only use temporary URLs if the disk supports them (cloud storage)
            // Local disks don't support getTemporaryUrl() and will throw an error
            if ($this->diskSupportsTemporaryUrls($media->disk)) {
                $expiration = config('filament-lms.media.signed_url_expiration', 60);

                try {
                    if ($conversion) {
                        return $media->getTemporaryUrl(now()->addMinutes($expiration), $conversion);
                    }

                    return $media->getTemporaryUrl(now()->addMinutes($expiration));
                } catch (\Exception $e) {
                    // If temporary URL generation fails, fall back to regular URL
                    // This can happen if credentials are missing or misconfigured
                    // No need to log here - we'll log if the fallback also fails
                }
            }
        }

        // Fall back to regular URLs (works for both local and cloud storage)
        try {
            if ($conversion) {
                return $media->getUrl($conversion);
            }

            return $media->getUrl();
        } catch (\Exception $e) {
            // If getUrl() also fails (e.g., S3 not configured), return null
            // This prevents the entire page from breaking due to media issues
            Log::warning('Failed to get media URL, returning null (page will show fallback/sample image)', [
                'model' => get_class($this),
                'model_id' => $this->id ?? null,
                'collection' => $collection,
                'disk' => $media->disk,
                'error' => $e->getMessage(),
                'exception' => $e,
                'suggestion' => 'Check storage disk configuration. If using S3, ensure AWS credentials and bucket are configured. If testing locally, consider setting MEDIA_DISK=public in .env',
            ]);

            return null;
        }
    }

    /**
     * Check if a storage disk supports temporary URLs.
     * Cloud storage (S3, etc.) supports this, local storage does not.
     *
     * @param  string  $diskName  The name of the disk to check
     */
    protected function diskSupportsTemporaryUrls(string $diskName): bool
    {
        $diskConfig = config("filesystems.disks.{$diskName}");

        if (! $diskConfig) {
            return false;
        }

        $driver = $diskConfig['driver'] ?? 'local';

        // Cloud storage drivers that support temporary URLs
        $cloudDrivers = ['s3', 's3-custom', 'gcs', 'azure', 'rackspace', 'dropbox'];

        return in_array($driver, $cloudDrivers);
    }
}
