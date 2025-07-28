<?php

namespace Tapp\FilamentLms\Resources\DocumentResource\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Resources\DocumentResource;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;
        $data = $this->data;

        if ($record && isset($data['media_name']) && ! empty($data['media_name'])) {
            $media = $record->getFirstMedia();
            if ($media) {
                $media->update(['name' => $data['media_name']]);
            }
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->record;

        if ($record) {
            $media = $record->getFirstMedia();
            if ($media) {
                $data['media_name'] = $media->name;
            }
        }

        return $data;
    }
}
