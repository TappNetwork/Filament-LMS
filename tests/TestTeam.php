<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Illuminate\Database\Eloquent\Model;

class TestTeam extends Model
{
    protected $table = 'teams';

    protected $guarded = [];
}
