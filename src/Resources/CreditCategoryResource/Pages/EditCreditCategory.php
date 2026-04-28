<?php

namespace Tapp\FilamentLms\Resources\CreditCategoryResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\CreditCategoryResource;

class EditCreditCategory extends EditRecord
{
    protected static string $resource = CreditCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
