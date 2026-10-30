<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Course order chosen on the LMS plugin, shared with the learner catalog.
 */
final class CourseListingSort
{
    /**
     * @param  (Closure(Builder, string): Builder)|string  $sort
     */
    public function __construct(
        public Closure|string $sort,
        public string $direction = 'asc',
    ) {}
}
