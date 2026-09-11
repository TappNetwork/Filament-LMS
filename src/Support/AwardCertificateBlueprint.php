<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

final readonly class AwardCertificateBlueprint
{
    /**
     * @param  list<string>  $logoPaths
     * @param  array<string, mixed>  $border
     * @param  array<string, mixed>  $header
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
        public bool $includeSignatures,
        public array $border,
        public array $header,
        public ?string $headerImagePath,
    ) {}
}
