<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tapp\FilamentLms\Database\Factories\DocumentFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;
use Tapp\FilamentLms\Traits\HasMediaUrl;

class Document extends Model implements HasMedia
{
    use BelongsToTenant;
    use HasFactory;
    use HasMediaUrl;
    use InteractsWithMedia;
    use SoftDeletes;

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
        $media = $this->getFirstMedia();

        if (! $media) {
            return 'Unknown';
        }

        $mime = $media->mime_type;

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
        // Try preview collection first
        $previewUrl = $this->getMediaUrl('preview');
        if ($previewUrl) {
            return $previewUrl;
        }

        // Fallback: use the main file collection
        return $this->getMediaUrl('default');
    }
}
