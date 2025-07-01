<?php

namespace Tapp\FilamentLms\Resources\ImageResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\ImageResource;

class EditImage extends EditRecord
{
    protected static string $resource = ImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
} 