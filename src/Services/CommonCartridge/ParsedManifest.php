<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * Result of parsing imsmanifest.xml.
 *
 * @param  array<string, ResourceData>  $resources  identifier => ResourceData
 * @param  list<LessonStructure>  $lessons
 * @param  list<FrameResourceEntry>  $frameResources  Links/documents from Articulate frame.xml resource_data
 */
final class ParsedManifest
{
    /**
     * @param  array<string, ResourceData>  $resources
     * @param  list<LessonStructure>  $lessons
     * @param  list<FrameResourceEntry>  $frameResources
     */
    public function __construct(
        public string $courseTitle,
        public ?string $courseDescription,
        public array $resources,
        public array $lessons,
        public array $frameResources = [],
    ) {}
}
