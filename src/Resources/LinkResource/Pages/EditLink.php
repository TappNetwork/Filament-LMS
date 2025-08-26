<?php

namespace Tapp\FilamentLms\Resources\LinkResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\LinkResource;

class EditLink extends EditRecord
{
    protected static string $resource = LinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
