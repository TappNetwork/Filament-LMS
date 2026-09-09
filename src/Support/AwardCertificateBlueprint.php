<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

final readonly class AwardCertificateBlueprint
{
    /**
     * @param  list<string>  $logoPaths
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $templateName,
        public array $logoPaths,
        public string $certifyingLine,
        public string $completedLine,
        public string $description,
        public bool $includeCourseName,
    ) {}
}
