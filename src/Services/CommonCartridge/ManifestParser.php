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
        $extractedPath = mb_rtrim($extractedPath, '/');
        $manifestPath = $extractedPath.'/imsmanifest.xml';
        if (! is_file($manifestPath)) {
            $html5Parser = new Html5PackageParser;
            if ($html5Parser->supports($extractedPath)) {
                return $html5Parser->parse($extractedPath);
            }

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

        $preferredLaunchHref = $this->resolvePreferredLaunchHref($resources, $extractedPath, $xml);

        return new ParsedManifest(
            courseTitle: $title,
            courseDescription: $description,
            resources: $resources,
            lessons: $lessons,
            frameResources: $frameResources,
            preferredLaunchHref: $preferredLaunchHref,
        );
    }

    /**
     * Prefer the SCORM driver shell (manifest href) so Rise content loads inside a supported LMS context.
     */
    private function resolvePreferredLaunchHref(array $resources, string $extractedPath, SimpleXMLElement $xml): ?string
    {
        $driverLaunch = 'scormdriver/indexAPI.html';
        $riseContentLaunch = 'scormcontent/index.html';

        if (is_file($extractedPath.'/'.$driverLaunch)) {
            return $driverLaunch;
        }

        foreach ($resources as $resource) {
            if ($resource->href === $driverLaunch || in_array($driverLaunch, $resource->fileHrefs, true)) {
                return $driverLaunch;
            }
        }

        if (is_file($extractedPath.'/'.$riseContentLaunch)) {
            return $riseContentLaunch;
        }

        $org = $this->getDefaultOrganization($xml);
        if ($org !== null) {
            $orgId = $this->itemAttribute($org, 'identifier');
            if (str_contains(mb_strtolower($orgId), 'articulate_rise')) {
                return $driverLaunch;
            }
        }

        foreach ($resources as $resource) {
            if (in_array($riseContentLaunch, $resource->fileHrefs, true) || $resource->href === $riseContentLaunch) {
                return $riseContentLaunch;
            }
        }

        return null;
    }

    private function extractCourseTitle(SimpleXMLElement $xml): string
    {
        $metadata = $xml->metadata ?? null;
        if ($metadata === null) {
            return $this->courseTitleFromOrg($xml);
        }

        $imsmdChildren = $metadata->children(self::NS_IMSMD);
        $lom = isset($imsmdChildren[0]) ? $imsmdChildren[0] : null;
        if ($lom === null) {
            return $this->courseTitleFromOrg($xml);
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
        $xml->registerXPathNamespace('imscp', self::NS_IMSCP);
        $out = [];

        foreach ($xml->xpath('//imscp:resource') as $res) {
            $identifier = (string) ($res['identifier'] ?? $res->attributes()['identifier'] ?? '');
            $type = (string) ($res['type'] ?? $res->attributes()['type'] ?? 'webcontent');
            $href = (string) ($res['href'] ?? $res->attributes()['href'] ?? '');
            $adlcp = $res->attributes(self::NS_ADLCP);
            $scormType = $adlcp !== null && isset($adlcp['scormtype']) ? (string) $adlcp['scormtype'] : null;

            $fileHrefs = [];
            $escapedIdentifier = str_replace("'", "\\'", $identifier);
            $fileNodes = $xml->xpath(sprintf('//imscp:resource[@identifier=\'%s\']//imscp:file', $escapedIdentifier));
            if (is_array($fileNodes)) {
                foreach ($fileNodes as $file) {
                    $fileHref = (string) ($file->attributes()['href'] ?? '');
                    if ($fileHref !== '' && ! in_array($fileHref, $fileHrefs, true)) {
                        $fileHrefs[] = $fileHref;
                    }
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

        $items = $this->organizationItems($org);
        $itemCount = count($items);

        if ($itemCount === 0) {
            return [];
        }

        $lessons = [];
        $orderLesson = 0;

        foreach ($items as $item) {
            $itemIdentifierRef = $this->itemAttribute($item, 'identifierref');
            $itemTitle = $this->getItemTitle($item);
            $childItems = $this->organizationItems($item);
            $childCount = count($childItems);

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
                    $ref = $this->itemAttribute($child, 'identifierref');
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
        $orgs = $this->organizationsElement($xml);
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

    private function organizationsElement(SimpleXMLElement $xml): ?SimpleXMLElement
    {
        $imscp = $xml->children(self::NS_IMSCP);
        if (isset($imscp->organizations)) {
            return $imscp->organizations;
        }

        return $xml->organizations ?? null;
    }

    private function getItemTitle(SimpleXMLElement $item): string
    {
        return $this->elementText($item, 'title');
    }

    private function courseTitleFromOrg(SimpleXMLElement $xml): string
    {
        $org = $this->getDefaultOrganization($xml);
        if ($org !== null) {
            $title = $this->elementText($org, 'title');
            if ($title !== '') {
                return $title;
            }
        }

        return 'Imported Course';
    }

    /**
     * @return list<SimpleXMLElement>
     */
    private function organizationItems(SimpleXMLElement $element): array
    {
        $items = [];
        foreach ($element->children(self::NS_IMSCP) as $child) {
            if ($child->getName() === 'item') {
                $items[] = $child;
            }
        }
        if ($items !== []) {
            return $items;
        }

        foreach ($element->item as $item) {
            $items[] = $item;
        }

        return $items;
    }

    private function itemAttribute(SimpleXMLElement $item, string $name): string
    {
        foreach ($item->attributes() as $attributeName => $value) {
            if ((string) $attributeName === $name) {
                return (string) $value;
            }
        }

        return '';
    }

    private function elementText(SimpleXMLElement $element, string $name): string
    {
        $value = $element->{$name} ?? null;
        if ($value !== null && (string) $value !== '') {
            return mb_trim((string) $value);
        }

        $imscp = $element->children(self::NS_IMSCP);
        $namespaced = $imscp->{$name} ?? null;
        if ($namespaced !== null && (string) $namespaced !== '') {
            return mb_trim((string) $namespaced);
        }

        return '';
    }
}
