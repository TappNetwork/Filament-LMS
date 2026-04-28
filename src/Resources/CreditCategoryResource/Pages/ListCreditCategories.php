<?php

namespace Tapp\FilamentLms\Resources\CreditCategoryResource\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tapp\FilamentLms\Resources\CreditCategoryResource;

class ListCreditCategories extends ListRecords
{
    protected static string $resource = CreditCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
