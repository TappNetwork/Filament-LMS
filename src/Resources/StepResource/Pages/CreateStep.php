<?php

namespace Tapp\FilamentLms\Resources\StepResource\Pages;

use Tapp\FilamentLms\Resources\StepResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStep extends CreateRecord
{
    protected static string $resource = StepResource::class;

    protected function fillForm(): void
{
    parent::fillForm();

    $request = request();

    $this->form->fill($request->query());
}
}
