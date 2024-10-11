<?php

namespace App\Filament\App\Resources\TrainerResource\Pages;

use App\Filament\App\Resources\TrainerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainer extends ViewRecord
{
    protected static string $resource = TrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
