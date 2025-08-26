<?php

namespace Tapp\FilamentLms\Resources\TestResource\Pages;

use Filament\Actions\DeleteAction;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\TestResource;

class EditTest extends EditRecord
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            Action::make('create_rubric')
                ->color('success')
                ->action(function () {
                    return redirect(route('filament.admin.pages.create-rubric', ['test' => $this->record]));
                })
                ->visible(function () {
                    return ! $this->record->filament_form_user_id;
                }),
        ];
    }
}
