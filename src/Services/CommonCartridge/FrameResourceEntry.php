<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * One link/document entry from Articulate frame.xml resource_data.
 */
final class FrameResourceEntry
{
    public function __construct(
        public string $url,
        public string $title,
    ) {}
}
