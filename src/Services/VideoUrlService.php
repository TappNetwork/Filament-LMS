<?php

namespace Tapp\FilamentLms\Services;

class VideoUrlService
{
    /**
     * Convert standard YouTube/Vimeo URLs to embed URLs
     */
    public static function convertToEmbedUrl(string $url): string
    {
        // If it's already an embed URL, return as is
        if (str_contains($url, 'youtube.com/embed/') || str_contains($url, 'player.vimeo.com/video/')) {
            return $url;
        }

        // Convert YouTube watch URLs to embed URLs
        if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Convert YouTube short URLs to embed URLs
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
            return 'https://www.youtube.com/embed/' . $matches[1];
        }

        // Convert Vimeo URLs to embed URLs
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return 'https://player.vimeo.com/video/' . $matches[1];
        }

        // If no conversion is possible, return the original URL
        return $url;
    }

    /**
     * Check if URL is a valid embed URL
     */
    public static function isValidEmbedUrl(string $url): bool
    {
        return preg_match('/(https:\/\/www\.youtube\.com\/embed\/|https:\/\/player\.vimeo\.com\/video\/)([a-zA-Z0-9_-]+)/', $url);
    }

    /**
     * Validate and convert a video URL
     * 
     * @throws \Exception if conversion fails or URL is invalid
     */
    public static function validateAndConvert(string $url): string
    {
        $originalUrl = $url;
        $convertedUrl = self::convertToEmbedUrl($url);
        
        // Validate that conversion was successful
        if ($convertedUrl === $originalUrl && !self::isValidEmbedUrl($convertedUrl)) {
            throw new \Exception('Automatic conversion to embed link failed. Please try entering the link that the video is embedded from.');
        }
        
        // Validate the converted URL matches embed format
        if (!self::isValidEmbedUrl($convertedUrl)) {
            throw new \Exception('Automatic conversion to embed link failed. Please try entering the link that the video is embedded from.');
        }
        
        return $convertedUrl;
    }

    /**
     * Get helper text for video URL input
     */
    public static function getHelperText(): string
    {
        return 'Enter a YouTube or Vimeo video URL and it will be converted to embed link.<br/>Examples:<br/>• https://www.youtube.com/watch?v=ABC123<br/>• https://youtu.be/ABC123<br/>• https://vimeo.com/123456';
    }
}
