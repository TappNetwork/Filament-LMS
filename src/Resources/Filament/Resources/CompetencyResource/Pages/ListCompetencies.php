<?php

namespace App\Filament\Resources\CompetencyResource\Pages;

use App\Filament\Resources\CompetencyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCompetencies extends ListRecords
{
    protected static string $resource = CompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
