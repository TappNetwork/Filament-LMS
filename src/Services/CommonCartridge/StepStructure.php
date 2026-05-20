<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * One step in the parsed structure (item with optional resource reference).
 * For Articulate packages, slideId is the slide identifier used in html5/data/js/{slideId}.js.
 */
final class StepStructure
{
    public function __construct(
        public string $title,
        public ?string $resourceIdentifier = null,
        public int $order = 0,
        public ?string $slideId = null,
    ) {}
}
