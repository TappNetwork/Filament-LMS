<?php

namespace Tapp\FilamentLms\Resources\StepResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tapp\FilamentLms\Resources\StepResource;

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
