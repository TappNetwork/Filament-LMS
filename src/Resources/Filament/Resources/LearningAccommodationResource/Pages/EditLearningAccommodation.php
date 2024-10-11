<?php

namespace App\Filament\Resources\LearningAccommodationResource\Pages;

use App\Filament\Resources\LearningAccommodationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLearningAccommodation extends EditRecord
{
    protected static string $resource = LearningAccommodationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
