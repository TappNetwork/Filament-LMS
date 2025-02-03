<?php

namespace Tapp\FilamentLms\Resources\DocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tapp\FilamentLms\Resources\DocumentResource;

class ListDocuments extends ListRecords
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
