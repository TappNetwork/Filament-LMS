<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tapp\FilamentLms\Database\Factories\LinkFactory;
use Tapp\FilamentLms\Jobs\GenerateLinkScreenshot;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Link extends Model implements HasMedia
{
    use HasFactory, SoftDeletes, InteractsWithMedia;

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

    protected static function booted()
    {
        static::saved(function ($link) {
            if ($link->wasChanged('url') || !$link->getFirstMedia('preview')) {
                GenerateLinkScreenshot::dispatch($link);
            }
        });
    }
}
