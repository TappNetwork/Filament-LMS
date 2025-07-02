<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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

    /**
     * Get the preview image URL, using the 'preview' collection if available, otherwise fallback to the generated preview.
     */
    public function getPreviewImageUrl(): ?string
    {
        $previewMedia = $this->getFirstMedia('preview');
        if ($previewMedia) {
            return $previewMedia->getUrl();
        }
        // Fallback: use the generated preview from the main file collection (assumed to be 'file')
        $mainMedia = $this->getFirstMedia();
        if ($mainMedia && method_exists($mainMedia, 'getGeneratedConversionUrl')) {
            // If you have a conversion (e.g., 'thumb'), you can use it here
            return $mainMedia->getUrl();
        }

        return null;
    }
}
