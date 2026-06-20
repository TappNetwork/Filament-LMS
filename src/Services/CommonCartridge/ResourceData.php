<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * Parsed resource from imsmanifest.xml <resource> element.
 */
final class ResourceData
{
    public function __construct(
        public string $identifier,
        public string $type,
        public string $href,
        /** @var list<string> */
        public array $fileHrefs = [],
        public ?string $scormType = null,
    ) {}
}
