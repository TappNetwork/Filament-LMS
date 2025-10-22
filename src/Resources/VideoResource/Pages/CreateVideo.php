<?php

namespace Tapp\FilamentLms\Resources\VideoResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;

class CreateVideo extends CreateRecord
{
    protected static string $resource = VideoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert and validate the URL
        $data['url'] = VideoUrlService::validateAndConvert($data['url']);
        
        return $data;
    }
}
