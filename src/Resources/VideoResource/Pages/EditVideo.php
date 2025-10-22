<?php

namespace Tapp\FilamentLms\Resources\VideoResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\VideoResource;
use Tapp\FilamentLms\Services\VideoUrlService;

class EditVideo extends EditRecord
{
    protected static string $resource = VideoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Convert and validate the URL
        $data['url'] = VideoUrlService::validateAndConvert($data['url']);
        
        return $data;
    }
}
