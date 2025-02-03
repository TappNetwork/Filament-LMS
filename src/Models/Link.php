<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tapp\FilamentLms\Database\Factories\LinkFactory;

class Link extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $table = 'lms_links';

    protected static function newFactory()
    {
        return LinkFactory::new();
    }

    public function step(): MorphTo
    {
        return $this->morphTo(Step::class);
    }
}
