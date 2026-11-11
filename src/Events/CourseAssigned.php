<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tapp\FilamentLms\Models\Course;

class CourseAssigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Model $user,
        public Course $course,
    ) {}
}
