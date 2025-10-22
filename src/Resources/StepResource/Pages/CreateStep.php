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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Convert material_type and material_id to material relationship
        if (isset($data['material_type']) && isset($data['material_id'])) {
            $data['material_type'] = $data['material_type'];
            $data['material_id'] = $data['material_id'];
        }

        return $data;
    }
}
