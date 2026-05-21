<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use InvalidArgumentException;

/**
 * Parses Articulate Storyline HTML5 publish output (no imsmanifest.xml).
 */
final class Html5PackageParser
{
    public function supports(string $extractedPath): bool
    {
        $root = mb_rtrim($extractedPath, '/');

        return ! is_file($root.'/imsmanifest.xml')
            && is_file($root.'/story_content/frame.xml');
    }

    public function parse(string $extractedPath): ParsedManifest
    {
        $root = mb_rtrim($extractedPath, '/');
        $frameParser = new ArticulateFrameParser;
        $lessons = $frameParser->parse($root);
        if ($lessons === null || $lessons === []) {
            throw new InvalidArgumentException('frame.xml is present but contains no lessons.');
        }

        $resources = [];
        $launchHref = $this->resolveLaunchHref($root);
        if ($launchHref !== null) {
            $resources['__html5_launch__'] = new ResourceData(
                identifier: '__html5_launch__',
                type: 'webcontent',
                href: $launchHref,
                fileHrefs: [$launchHref],
                scormType: 'sco',
            );
        }

        return new ParsedManifest(
            courseTitle: $this->extractTitle($root, $lessons),
            courseDescription: null,
            resources: $resources,
            lessons: $lessons,
            frameResources: $frameParser->parseResourceData($root),
            preferredLaunchHref: $launchHref,
        );
    }

    private function extractTitle(string $root, array $lessons): string
    {
        $metaPath = $root.'/meta.xml';
        if (is_file($metaPath)) {
            $xml = @simplexml_load_file($metaPath);
            if ($xml !== false) {
                $title = $xml->title ?? $xml->project['title'] ?? null;
                if ($title !== null && (string) $title !== '') {
                    return mb_trim((string) $title);
                }
            }
        }

        return $lessons[0]->title !== '' ? $lessons[0]->title : 'Imported Course';
    }

    private function resolveLaunchHref(string $root): ?string
    {
        foreach (['index.html', 'story.html'] as $candidate) {
            if (is_file($root.'/'.$candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
