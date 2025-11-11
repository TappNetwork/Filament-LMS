<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

class Image extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $guarded = [];

    protected $table = 'lms_images';
}
