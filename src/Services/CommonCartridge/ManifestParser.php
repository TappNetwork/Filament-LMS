<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use SimpleXMLElement;

/**
 * Parses imsmanifest.xml (IMS Content Packaging / Common Cartridge / SCORM 1.2).
 */
final class ManifestParser
{
    private const NS_IMSCP = 'http://www.imsproject.org/xsd/imscp_rootv1p1p2';

    private const NS_IMSMD = 'http://www.imsglobal.org/xsd/imsmd_rootv1p2p1';

    private const NS_ADLCP = 'http://www.adlnet.org/xsd/adlcp_rootv1p2';

    public function parse(string $extractedPath): ParsedManifest
    {
        $manifestPath = mb_rtrim($extractedPath, '/').'/imsmanifest.xml';
        if (! is_file($manifestPath)) {
            throw new InvalidArgumentException("imsmanifest.xml not found at: {$manifestPath}");
        }

        $xml = @simplexml_load_file($manifestPath);
        if ($xml === false) {
            throw new InvalidArgumentException('imsmanifest.xml is not valid XML.');
        }

        $xml->registerXPathNamespace('imscp', self::NS_IMSCP);
        $xml->registerXPathNamespace('imsmd', self::NS_IMSMD);
        $xml->registerXPathNamespace('adlcp', self::NS_ADLCP);

        $title = $this->extractCourseTitle($xml);
        $description = $this->extractCourseDescription($xml);
        $resources = $this->extractResources($xml);
        $lessons = $this->extractLessonsAndSteps($xml, $resources);

        $frameParser = new ArticulateFrameParser;
        $articulateLessons = $frameParser->parse($extractedPath);
        $frameResources = [];
        if ($articulateLessons !== null && $articulateLessons !== []) {
            $lessons = $articulateLessons;
            $frameResources = $frameParser->parseResourceData($extractedPath);
            $totalSteps = array_sum(array_map(fn ($l) => count($l->steps), $lessons));
            Log::channel('single')->info('CC import: using Articulate frame.xml', [
                'context' => 'cc-import',
                'lessons_count' => count($lessons),
                'steps_count' => $totalSteps,
                'frame_resources_count' => count($frameResources),
                'sample_steps' => array_slice(array_merge(...array_map(fn ($l) => array_map(fn ($s) => ['title' => $s->title, 'slideId' => $s->slideId], $l->steps), $lessons)), 0, 5),
            ]);
        } else {
            Log::channel('single')->info('CC import: Articulate frame.xml not used (missing or empty)', [
                'context' => 'cc-import',
                'frame_path' => mb_rtrim($extractedPath, '/').'/story_content/frame.xml',
                'frame_exists' => is_file(mb_rtrim($extractedPath, '/').'/story_content/frame.xml'),
            ]);
        }

        if ($lessons === []) {
            throw new InvalidArgumentException('Manifest has no organization or items.');
        }

        return new ParsedManifest(
            courseTitle: $title,
            courseDescription: $description,
            resources: $resources,
            lessons: $lessons,
            frameResources: $frameResources,
        );
    }

    private function extractCourseTitle(SimpleXMLElement $xml): string
    {
        $metadata = $xml->metadata ?? null;
        if ($metadata === null) {
            return 'Imported Course';
        }

        $imsmdChildren = $metadata->children(self::NS_IMSMD);
        $lom = isset($imsmdChildren[0]) ? $imsmdChildren[0] : null;
        if ($lom === null) {
            $org = $this->getDefaultOrganization($xml);
            if ($org !== null) {
                $titleEl = $org->title ?? null;
                if ($titleEl !== null && (string) $titleEl !== '') {
                    return mb_trim((string) $titleEl);
                }
            }

            return 'Imported Course';
        }

        $general = $lom->general ?? null;
        if ($general === null) {
            return 'Imported Course';
        }
        $title = $general->title ?? null;
        if ($title === null) {
            return 'Imported Course';
        }
        $langstring = $title->langstring ?? $title->children(self::NS_IMSMD)->langstring ?? null;
        if ($langstring !== null && (string) $langstring !== '') {
            return mb_trim((string) $langstring);
        }

        $org = $this->getDefaultOrganization($xml);
        if ($org !== null) {
            $t = $org->title ?? null;
            if ($t !== null && (string) $t !== '') {
                return mb_trim((string) $t);
            }
        }

        return 'Imported Course';
    }

    private function extractCourseDescription(SimpleXMLElement $xml): ?string
    {
        $metadata = $xml->metadata ?? null;
        if ($metadata === null) {
            return null;
        }
        $imsmdChildren = $metadata->children(self::NS_IMSMD);
        $lom = isset($imsmdChildren[0]) ? $imsmdChildren[0] : null;
        if ($lom === null) {
            return null;
        }
        $general = $lom->general ?? null;
        if ($general === null) {
            return null;
        }
        $description = $general->description ?? $general->children(self::NS_IMSMD)->description ?? null;
        if ($description === null) {
            return null;
        }
        $langstring = $description->langstring ?? $description->children(self::NS_IMSMD)->langstring ?? null;
        if ($langstring === null) {
            return null;
        }
        $text = mb_trim((string) $langstring);

        return $text === '' ? null : $text;
    }

    /**
     * @return array<string, ResourceData>
     */
    private function extractResources(SimpleXMLElement $xml): array
    {
        $resourcesEl = $xml->resources ?? null;
        if ($resourcesEl === null) {
            return [];
        }

        $out = [];
        foreach ($resourcesEl->resource as $res) {
            $identifier = (string) ($res['identifier'] ?? '');
            $type = (string) ($res['type'] ?? 'webcontent');
            $href = (string) ($res['href'] ?? '');
            $adlcp = $res->attributes(self::NS_ADLCP);
            $scormType = $adlcp !== null && isset($adlcp['scormtype']) ? (string) $adlcp['scormtype'] : null;

            $fileHrefs = [];
            foreach ($res->file as $file) {
                $h = (string) ($file['href'] ?? '');
                if ($h !== '') {
                    $fileHrefs[] = $h;
                }
            }

            if ($identifier !== '') {
                $out[$identifier] = new ResourceData(
                    identifier: $identifier,
                    type: $type,
                    href: $href,
                    fileHrefs: $fileHrefs,
                    scormType: $scormType,
                );
            }
        }

        return $out;
    }

    /**
     * @param  array<string, ResourceData>  $resources
     * @return list<LessonStructure>
     */
    private function extractLessonsAndSteps(SimpleXMLElement $xml, array $resources): array
    {
        $org = $this->getDefaultOrganization($xml);
        if ($org === null) {
            return [];
        }

        $items = $org->item ?? [];
        $itemCount = is_countable($items) ? count($items) : 0;

        if ($itemCount === 0) {
            return [];
        }

        $lessons = [];
        $orderLesson = 0;

        foreach ($items as $item) {
            $itemIdentifierRef = (string) ($item['identifierref'] ?? '');
            $itemTitle = $this->getItemTitle($item);
            $childItems = $item->item ?? [];
            $childCount = is_countable($childItems) ? count($childItems) : 0;

            if ($itemCount === 1 && $childCount === 0 && $itemIdentifierRef !== '') {
                $stepTitle = $itemTitle !== '' ? $itemTitle : 'Content';
                $step = new StepStructure(
                    title: $stepTitle,
                    resourceIdentifier: $itemIdentifierRef,
                    order: 0,
                );
                $lessons[] = new LessonStructure(
                    title: 'Content',
                    steps: [$step],
                    order: $orderLesson++,
                );
                break;
            }

            $steps = [];
            $orderStep = 0;
            if ($childCount > 0) {
                foreach ($childItems as $child) {
                    $ref = (string) ($child['identifierref'] ?? '');
                    $steps[] = new StepStructure(
                        title: $this->getItemTitle($child),
                        resourceIdentifier: $ref !== '' ? $ref : null,
                        order: $orderStep++,
                    );
                }
            } else {
                $steps[] = new StepStructure(
                    title: $itemTitle !== '' ? $itemTitle : 'Step',
                    resourceIdentifier: $itemIdentifierRef !== '' ? $itemIdentifierRef : null,
                    order: 0,
                );
            }

            $lessons[] = new LessonStructure(
                title: $itemTitle !== '' ? $itemTitle : 'Lesson '.($orderLesson + 1),
                steps: $steps,
                order: $orderLesson++,
            );
        }

        return $lessons;
    }

    private function getDefaultOrganization(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        $orgs = $xml->organizations ?? null;
        if ($orgs === null) {
            return null;
        }
        $defaultId = (string) ($orgs['default'] ?? '');
        foreach ($orgs->organization as $org) {
            $id = (string) ($org['identifier'] ?? '');
            if ($defaultId !== '' && $id === $defaultId) {
                return $org;
            }
        }
        $first = $orgs->organization[0] ?? null;

        return $first !== null ? $first : null;
    }

    private function getItemTitle(SimpleXMLElement $item): string
    {
        $title = $item->title ?? null;
        if ($title !== null && (string) $title !== '') {
            return mb_trim((string) $title);
        }

        return '';
    }

    private function courseTitleFromOrg(SimpleXMLElement $xml): string
    {
        $org = $this->getDefaultOrganization($xml);
        if ($org !== null) {
            $t = $org->title ?? null;
            if ($t !== null && (string) $t !== '') {
                return mb_trim((string) $t);
            }
        }

        return 'Imported Course';
    }
}
