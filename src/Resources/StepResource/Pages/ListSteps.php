<?php

namespace Tapp\FilamentLms\Resources\StepResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tapp\FilamentLms\Resources\StepResource;

class ListSteps extends ListRecords
{
    protected static string $resource = StepResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
