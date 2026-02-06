<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Dummy model used only to wrap the course progress report query in an
 * Eloquent Builder so Filament Tables accept it (they require Builder|Closure|null, not Query\Builder).
 */
class CourseProgressReportRow extends Model
{
    protected $table = 'report';

    public $timestamps = false;

    protected $keyType = 'string';

    public $incrementing = false;
}
