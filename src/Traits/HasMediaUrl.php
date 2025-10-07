<?php

namespace Tapp\FilamentLms\Traits;

trait HasMediaUrl
{
    /**
     * Get a media URL with support for signed URLs for private storage
     */
    public function getMediaUrl(string $collection, string $conversion = ''): ?string
    {
        $media = $this->getFirstMedia($collection);

        if (! $media) {
            return null;
        }

        // Check if signed URLs are enabled in config
        if (config('filament-lms.media.use_signed_urls', false)) {
            $expiration = config('filament-lms.media.signed_url_expiration', 60);

            if ($conversion) {
                return $media->getTemporaryUrl(now()->addMinutes($expiration), $conversion);
            }

            return $media->getTemporaryUrl(now()->addMinutes($expiration));
        }

        if ($conversion) {
            return $media->getUrl($conversion);
        }

        return $media->getUrl();
    }
}
