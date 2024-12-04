<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Tapp\FilamentLms\Database\Factories\VideoFactory;

class Video extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_videos';

    protected static function newFactory()
    {
        return VideoFactory::new();
            }

    public function step()
    {
        return $this->morphTo(Step::class);
    }

    public function getProviderAttribute()
    {
        // TODO determine provider from url
        return 'vimeo';
    }
}
