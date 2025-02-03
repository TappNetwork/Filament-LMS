<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentLms\Database\Factories\DocumentFactory;

class Document extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $guarded = [];

    protected $table = 'lms_documents';

    protected static function newFactory()
    {
        return DocumentFactory::new();
    }

    public function step(): MorphTo
    {
        return $this->morphTo(Step::class);
    }

    public function getTypeAttribute()
    {
        $mime = $this->getFirstMedia()->mime_type;

        // TODO also create mime2icon and put in an enum
        $mime2label = [
            'application/pdf' => 'PDF',
        ];

        return $mime2label[$mime] ?? $mime;
    }
}
