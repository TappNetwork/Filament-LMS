<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentLms\Traits\HasMediaUrl;

class Image extends Model implements HasMedia
{
    use HasFactory, HasMediaUrl, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected $table = 'lms_images';
}
