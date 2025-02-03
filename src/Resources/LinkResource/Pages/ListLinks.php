<?php

namespace Tapp\FilamentLms\Resources\LinkResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tapp\FilamentLms\Resources\LinkResource;

class ListLinks extends ListRecords
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
