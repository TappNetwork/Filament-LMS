<?php

namespace App\Filament\App\Resources\TrainerResource\Pages;

use App\Filament\App\Resources\TrainerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTrainer extends EditRecord
{
    protected static string $resource = TrainerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
