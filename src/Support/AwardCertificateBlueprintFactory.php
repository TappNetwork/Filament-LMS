<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Course;
use Throwable;

final class AwardCertificateBlueprintFactory
{
    /**
     * @return list<string>
     */
    public function awardKeys(?string $only = null): array
    {
        if (is_string($only) && $only !== '') {
            return [$only];
        }

        $configured = array_keys($this->configuredAwards());
        $used = $this->courseQuery()
            ->whereNotNull('award')
            ->where('award', '!=', '')
            ->distinct()
            ->orderBy('award')
            ->pluck('award')
            ->all();

        $keys = array_values(array_unique([
            'default',
            ...$configured,
            ...array_map(strval(...), $used),
        ]));

        sort($keys);

        return $keys;
    }

    public function make(string $awardKey): AwardCertificateBlueprint
    {
        $label = $this->labelFor($awardKey);
        $source = $this->viewSource($awardKey);
        $copy = $this->copyFrom($source);

        return new AwardCertificateBlueprint(
            key: $awardKey,
            label: $label,
            templateName: $label.' Certificate',
            logoPaths: $this->logoPaths($source),
            certifyingLine: $copy['certifying_line'],
            completedLine: $copy['completed_line'],
            description: $copy['description'],
            includeCourseName: $this->includesCourseName($source),
            includeSignatures: $this->includesSignatures($source),
            border: $this->borderFrom($source),
            header: $this->headerFrom($source),
            headerImagePath: $this->headerImagePath($source),
        );
    }

    public function resolveLogoAbsolutePath(string $path): ?string
    {
        $parsed = parse_url($path, PHP_URL_PATH);
        $relative = ltrim(is_string($parsed) && $parsed !== '' ? $parsed : $path, '/');

        if ($relative === '') {
            return null;
        }

        $candidates = [
            public_path($relative),
            base_path('public/'.$relative),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function configuredAwards(): array
    {
        $awards = config('filament-lms.awards', ['default' => 'Default']);

        if (! is_array($awards) || $awards === []) {
            return ['default' => 'Default'];
        }

        $normalized = [];

        foreach ($awards as $key => $label) {
            $normalized[(string) $key] = is_string($label) && $label !== ''
                ? $label
                : Str::headline(str_replace(['-', '_'], ' ', (string) $key));
        }

        return $normalized;
    }

    private function labelFor(string $awardKey): string
    {
        return $this->configuredAwards()[$awardKey]
            ?? Str::headline(str_replace(['-', '_'], ' ', $awardKey));
    }

    private function viewSource(string $awardKey): ?string
    {
        foreach ([$awardKey, 'default'] as $name) {
            $contents = $this->contentsFromPath($this->publishedViewPath($name))
                ?? $this->contentsFromView('filament-lms::certificates.'.$name);

            if ($contents !== null) {
                return $contents;
            }
        }

        return null;
    }

    private function publishedViewPath(string $awardKey): string
    {
        return resource_path('views/vendor/filament-lms/certificates/'.$awardKey.'.blade.php');
    }

    private function contentsFromPath(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        return is_string($contents) ? $contents : null;
    }

    private function contentsFromView(string $view): ?string
    {
        if (! view()->exists($view)) {
            return null;
        }

        try {
            $path = view($view)->getPath();
        } catch (Throwable) {
            return null;
        }

        return $this->contentsFromPath($path);
    }

    /**
     * @return list<string>
     */
    private function logoPaths(?string $source): array
    {
        $paths = [];

        if (is_string($source) && $source !== '') {
            $backgrounds = $this->backgroundAssetPaths($source);

            foreach ($this->imageAssetPaths($source) as $path) {
                if (in_array($path, $backgrounds, true) || $this->looksLikeHeaderAsset($path)) {
                    continue;
                }

                $paths[] = $path;
            }
        }

        if ($paths === []) {
            $configured = config('filament-lms.certificate_logo') ?: config('filament-lms.brand_logo');

            if (is_string($configured) && $configured !== '') {
                $paths[] = $configured;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @return list<string>
     */
    private function imageAssetPaths(string $source): array
    {
        preg_match_all(
            '/<img\b[^>]*\bsrc\s*=\s*[\'"]\s*\{\{\s*asset\(\s*[\'"]([^\'"]+)[\'"]/i',
            $source,
            $matches,
        );

        return $this->normalizeAssetPaths($matches[1]);
    }

    /**
     * @return list<string>
     */
    private function backgroundAssetPaths(string $source): array
    {
        preg_match_all(
            '/background-image\s*:\s*url\(\s*\{\{\s*asset\(\s*[\'"]([^\'"]+)[\'"]/i',
            $source,
            $matches,
        );

        return $this->normalizeAssetPaths($matches[1]);
    }

    /**
     * @param  list<string>  $paths
     * @return list<string>
     */
    private function normalizeAssetPaths(array $paths): array
    {
        $normalized = [];

        foreach ($paths as $path) {
            $path = trim($path);

            if ($path === '') {
                continue;
            }

            $normalized[] = $path;
        }

        return $normalized;
    }

    private function looksLikeHeaderAsset(string $path): bool
    {
        return str_contains(Str::lower($path), 'header');
    }

    /**
     * @return array{certifying_line: string, completed_line: string, description: string}
     */
    private function copyFrom(?string $source): array
    {
        $defaults = $this->defaultCopy();

        if (! is_string($source) || $source === '') {
            return $defaults;
        }

        $certifying = $defaults['certifying_line'];
        $completed = $defaults['completed_line'];
        $description = $defaults['description'];
        $foundCompleted = false;

        foreach ($this->extractCopyStrings($source) as $string) {
            if ($this->isTitleCopy($string)) {
                continue;
            }

            if ($this->isCertifyingCopy($string)) {
                $certifying = $string;

                continue;
            }

            if ($this->isCompletedCopy($string)) {
                $completed = $this->normalizeCompletedCopy($string);
                $foundCompleted = true;

                continue;
            }

            if (Str::length($string) > 40) {
                $description = $string;
            }
        }

        if ($description !== '' && ! $foundCompleted) {
            $completed = '';
        }

        return [
            'certifying_line' => $certifying,
            'completed_line' => $completed,
            'description' => $description,
        ];
    }

    /**
     * @return array{certifying_line: string, completed_line: string, description: string}
     */
    private function defaultCopy(): array
    {
        $tokenSet = CertificateBuilder::tokenSet();
        $copy = config('certificate-builder.token_sets.'.$tokenSet.'.default_copy', []);

        if (! is_array($copy)) {
            $copy = [];
        }

        return [
            'certifying_line' => is_string($copy['certifying_line'] ?? null) && $copy['certifying_line'] !== ''
                ? $copy['certifying_line']
                : 'This certifies that',
            'completed_line' => is_string($copy['completed_line'] ?? null) && $copy['completed_line'] !== ''
                ? $copy['completed_line']
                : 'has successfully completed',
            'description' => is_string($copy['description'] ?? null) ? $copy['description'] : '',
        ];
    }

    /**
     * @return list<string>
     */
    private function extractCopyStrings(string $source): array
    {
        $strings = [];

        preg_match_all('/__\(\s*[\'"](.+?)[\'"]\s*\)/s', $source, $matches);

        foreach ($matches[1] as $string) {
            $decoded = trim(stripcslashes((string) $string));

            if ($decoded !== '') {
                $strings[] = $decoded;
            }
        }

        if (preg_match_all('/<p\b[^>]*>(.*?)<\/p>/si', $source, $matches)) {
            foreach ($matches[1] as $html) {
                $text = $this->plainText((string) $html);

                if ($text !== '') {
                    $strings[] = $text;
                }
            }
        }

        return $strings;
    }

    private function plainText(string $html): string
    {
        $html = preg_replace('/\{\{.*?\}\}/s', '', $html) ?? $html;
        $html = preg_replace('/\{!!.*?!!\}/s', '', $html) ?? $html;
        $html = preg_replace('/@\w+(?:\([^)]*\))?/', '', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function isTitleCopy(string $string): bool
    {
        $normalized = Str::upper(trim($string));

        return in_array($normalized, [
            'CERTIFICATE',
            'CERTIFICATE OF COMPLETION',
            'OF COMPLETION',
        ], true);
    }

    private function isCertifyingCopy(string $string): bool
    {
        $normalized = Str::lower($string);

        return str_contains($normalized, 'awarded to')
            || str_contains($normalized, 'certifies that');
    }

    private function isCompletedCopy(string $string): bool
    {
        $normalized = Str::lower($string);

        return str_contains($normalized, 'successfully completed')
            || str_contains($normalized, 'has completed');
    }

    private function normalizeCompletedCopy(string $string): string
    {
        $normalized = trim(preg_replace('/\s+on\s*\.?$/i', '', $string) ?? $string);
        $normalized = trim($normalized, " \t\n\r\0\x0B.");

        return $normalized !== '' ? $normalized : 'has successfully completed';
    }

    private function includesCourseName(?string $source): bool
    {
        if (! is_string($source) || $source === '') {
            return true;
        }

        return str_contains($source, '$course->name')
            || str_contains($source, '$course[\'name\']');
    }

    /**
     * @return array<string, mixed>
     */
    private function borderFrom(?string $source): array
    {
        $border = [
            'style' => 'double',
            'color' => '#a1a1aa',
            'width' => 8,
            'inner_color' => '#d4d4d8',
            'inner_width' => 4,
            'inner_inset' => 10,
            'gradient' => '',
        ];

        if (! is_string($source) || $source === '') {
            return $border;
        }

        if (preg_match('/bg-linear-to-r\s+from-([a-z0-9-]+)\s+via-([a-z0-9-]+)\s+to-([a-z0-9-]+)/', $source, $matches) === 1) {
            $from = $this->tailwindColor($matches[1]);
            $via = $this->tailwindColor($matches[2]);
            $to = $this->tailwindColor($matches[3]);

            if ($from !== null && $via !== null && $to !== null) {
                return [
                    ...$border,
                    'style' => 'gradient',
                    'width' => 6,
                    'color' => $from,
                    'gradient' => 'linear-gradient(to right, '.$from.', '.$via.', '.$to.')',
                ];
            }
        }

        if (preg_match('/\bstyle\s*=\s*[\'"][^\'"]*background:\s*(#[0-9a-fA-F]{3,6})\b/', $source, $matches) === 1) {
            return [
                ...$border,
                'style' => 'solid',
                'color' => $matches[1],
                'width' => 6,
            ];
        }

        return $border;
    }

    /**
     * @return array<string, mixed>
     */
    private function headerFrom(?string $source): array
    {
        $header = [
            'enabled' => false,
            'height' => 220,
            'background_color' => '',
            'background_size' => 'cover',
            'background_position' => 'center',
            'title_bind' => '',
            'title' => '',
            'title_color' => '#ffffff',
            'title_size' => 36,
            'title_transform' => 'none',
            'subtitle' => '',
            'subtitle_color' => '#111827',
            'subtitle_size' => 28,
        ];

        if (! is_string($source) || $source === '' || $this->headerImagePath($source) === null) {
            return $header;
        }

        $header['enabled'] = true;

        if (preg_match('/background-size\s*:\s*([^;]+)/i', $source, $matches) === 1) {
            $header['background_size'] = trim($matches[1]);
        }

        if (preg_match('/background-position\s*:\s*([^;]+)/i', $source, $matches) === 1) {
            $header['background_position'] = trim($matches[1]);
        }

        if (preg_match('/background-color\s*:\s*(#[0-9a-fA-F]{3,6})/i', $source, $matches) === 1) {
            $header['background_color'] = $matches[1];
        }

        if (preg_match('/min-height\s*:\s*(\d+)px/i', $source, $matches) === 1) {
            $header['height'] = max(40, (int) $matches[1]);
        }

        if (str_contains($source, '$course->name') || str_contains($source, '$course[\'name\']')) {
            $header['title_bind'] = 'course_name';
            $header['title_transform'] = str_contains($source, 'uppercase') ? 'uppercase' : 'none';
        }

        if (preg_match('/__\(\s*[\'"]CERTIFICATE OF COMPLETION[\'"]\s*\)/', $source) === 1) {
            $header['subtitle'] = 'CERTIFICATE OF COMPLETION';
        }

        return $header;
    }

    private function headerImagePath(?string $source): ?string
    {
        if (! is_string($source) || $source === '') {
            return null;
        }

        return $this->backgroundAssetPaths($source)[0] ?? null;
    }

    private function tailwindColor(string $token): ?string
    {
        return match ($token) {
            'lime-400' => '#a3e635',
            'sky-500' => '#0ea5e9',
            'cyan-300' => '#67e8f9',
            'red-400' => '#f87171',
            'red-500' => '#ef4444',
            'red-300' => '#fca5a5',
            'zinc-400' => '#a1a1aa',
            'zinc-300' => '#d4d4d8',
            default => null,
        };
    }

    private function includesSignatures(?string $source): bool
    {
        if (! is_string($source) || $source === '') {
            return false;
        }

        if (str_contains($source, 'filament-lms.certificate_show_signatures')) {
            return (bool) config('filament-lms.certificate_show_signatures', false);
        }

        return (bool) preg_match('/signature/i', $source);
    }

    /**
     * @return Builder<Course>
     */
    private function courseQuery(): Builder
    {
        return Course::query()->withoutTenantScope();
    }
}
