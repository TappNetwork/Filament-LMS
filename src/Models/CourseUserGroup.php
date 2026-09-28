<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $course_id
 * @property int $user_group_id
 * @property bool $is_default
 */
class CourseUserGroup extends Pivot
{
    protected $table = 'lms_course_user_group';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }
}
