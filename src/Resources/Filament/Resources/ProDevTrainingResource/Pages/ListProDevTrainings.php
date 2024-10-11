<?php

namespace App\Filament\Resources\ProDevTrainingResource\Pages;

use App\Filament\Resources\ProDevTrainingResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProDevTrainings extends ListRecords
{
    protected static string $resource = ProDevTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
