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
        // Convert the URL (validation already happened in the form rules)
        $data['url'] = VideoUrlService::convertToEmbedUrl($data['url']);

        return $data;
    }
}
