<?php

namespace App\Filament\Resources\LearningAccommodationResource\Pages;

use App\Filament\Resources\LearningAccommodationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLearningAccommodations extends ListRecords
{
    protected static string $resource = LearningAccommodationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
