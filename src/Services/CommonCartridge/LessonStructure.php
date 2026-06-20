<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services\CommonCartridge;

/**
 * One lesson (module) in the parsed structure.
 *
 * @param  list<StepStructure>  $steps
 */
final class LessonStructure
{
    /**
     * @param  list<StepStructure>  $steps
     */
    public function __construct(
        public string $title,
        public array $steps,
        public int $order = 0,
    ) {}
}
