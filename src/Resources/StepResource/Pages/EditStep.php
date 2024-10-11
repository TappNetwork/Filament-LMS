<?php

namespace Tapp\FilamentLms\Resources\StepResource\Pages;

use Tapp\FilamentLms\Resources\StepResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStep extends EditRecord
{
    protected static string $resource = StepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
