<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Tapp\FilamentLms\Database\Factories\VideoFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

class Video extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_videos';

    protected static function newFactory()
    {
        return VideoFactory::new();
    }

    public function step(): MorphTo
    {
        return $this->morphTo(Step::class);
    }

    public function getProviderAttribute(): string
    {
        $url = $this->url ?? '';

        if (str_contains($url, 'youtube.com') || str_contains($url, 'youtu.be')) {
            return 'youtube';
        }

        if (str_contains($url, 'vimeo.com') || str_contains($url, 'player.vimeo.com')) {
            return 'vimeo';
        }

        return 'external';
    }
}
