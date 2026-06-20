<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * Parses Articulate Storyline's story_content/frame.xml to extract the course menu
 * (lessons and steps) when the SCORM manifest only defines a single item.
 *
 * Structure: nav_data > outline > links > slidelink (expand="true" = lesson)
 *   > links > slidelink (expand="false" = step)
 */
final class ArticulateFrameParser
{
    /**
     * Parse frame.xml if present. Returns list of lessons with steps, or null if not found/invalid.
     *
     * @return list<LessonStructure>|null
     */
    public function parse(string $extractedPath): ?array
    {
        $path = mb_rtrim($extractedPath, '/').'/story_content/frame.xml';
        if (! is_file($path)) {
            return null;
        }

        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return null;
        }

        $navData = $xml->nav_data ?? null;
        if ($navData === null) {
            return null;
        }
        $outline = $navData->outline ?? null;
        if ($outline === null) {
            return null;
        }
        $links = $outline->links ?? null;
        if ($links === null) {
            return null;
        }

        $lessons = [];
        $lessonOrder = 0;
        foreach ($links->slidelink as $slidelink) {
            $expand = (string) ($slidelink['expand'] ?? '');
            $displayText = $this->decodeDisplayText((string) ($slidelink['displaytext'] ?? ''));

            if (mb_strtolower($expand) === 'true') {
                $steps = [];
                $stepOrder = 0;
                $childLinks = $slidelink->links ?? null;
                if ($childLinks !== null) {
                    foreach ($childLinks->slidelink as $child) {
                        $slideIdRaw = (string) ($child['slideid'] ?? '');
                        $slideId = $slideIdRaw !== '' ? $this->extractSlideId($slideIdRaw) : null;
                        $steps[] = new StepStructure(
                            title: $this->decodeDisplayText((string) ($child['displaytext'] ?? '')),
                            resourceIdentifier: null,
                            order: $stepOrder++,
                            slideId: $slideId,
                        );
                    }
                }
                $lessons[] = new LessonStructure(
                    title: $displayText !== '' ? $displayText : 'Lesson '.($lessonOrder + 1),
                    steps: $steps,
                    order: $lessonOrder++,
                );
            }
        }

        return $lessons;
    }

    /**
     * Parse frame.xml resource_data if present. Returns list of url/title entries (links and documents).
     *
     * @return list<FrameResourceEntry>
     */
    public function parseResourceData(string $extractedPath): array
    {
        $path = mb_rtrim($extractedPath, '/').'/story_content/frame.xml';
        if (! is_file($path)) {
            return [];
        }

        $xml = @simplexml_load_file($path);
        if ($xml === false) {
            return [];
        }

        $resourceData = $xml->resource_data ?? null;
        if ($resourceData === null) {
            return [];
        }
        $resources = $resourceData->resources ?? null;
        if ($resources === null) {
            return [];
        }

        $entries = [];
        foreach ($resources->resource as $res) {
            $url = mb_trim((string) ($res['url'] ?? ''));
            $title = $this->decodeDisplayText((string) ($res['title'] ?? ''));
            if ($url !== '') {
                $entries[] = new FrameResourceEntry(url: $url, title: $title !== '' ? $title : $url);
            }
        }

        return $entries;
    }

    private function decodeDisplayText(string $text): string
    {
        $text = html_entity_decode($text, ENT_XML1 | ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return mb_trim($text);
    }

    /** Extract slide id (last segment) from slideid e.g. "_player.6Oszjf3XJTp.6TW1ASrmWOP" -> "6TW1ASrmWOP". */
    private function extractSlideId(string $slideIdAttr): ?string
    {
        $parts = explode('.', $slideIdAttr);
        $last = end($parts);

        return $last !== '' ? $last : null;
    }
}
