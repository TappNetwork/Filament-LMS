<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Fakes;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class FakeCertificateTemplate extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'certificate_templates';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('logo_1')->singleFile();
        $this->addMediaCollection('logo_2')->singleFile();
        $this->addMediaCollection('logo_3')->singleFile();
        $this->addMediaCollection('header')->singleFile();
    }
}
