<?php

namespace Tapp\FilamentLms\Resources\VideoResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;
use Illuminate\Validation\ValidationException;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert and validate the URL
        $result = VideoUrlService::validateAndConvertWithErrors($data['url']);
        
        if (!empty($result['errors'])) {
            // Throw a validation exception that Filament can handle
            throw new \Illuminate\Validation\ValidationException(
                validator([], []),
                $result['errors']
            );
        }
        
        $data['url'] = $result['url'];
        return $data;
    }
}
