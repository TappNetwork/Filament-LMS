<?php

namespace App\Filament\Resources\ProDevTrainingResource\Pages;

use App\Filament\Resources\ProDevTrainingResource;
use App\Notifications\ProDevTraining\ScheduleUpdated;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Spatie\CalendarLinks\Link;

class EditProDevTraining extends EditRecord
{
    protected static string $resource = ProDevTrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $description = 'Training Description: '.
            $data['description'].
            ' Meeting Link: '.
            $data['zoom_link'];

        $link = Link::create($data['name'], Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']))
            ->description($description);

        $data['google_calendar_link'] = $link->google();
        $data['ics_calendar_link'] = $link->ics();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var \App\Models\ProDevTraining $record */
        $sendScheduleUpdate = Carbon::parse($record->starts_at) != Carbon::parse($data['starts_at']) ||
            Carbon::parse($record->ends_at) != Carbon::parse($data['ends_at']) ||
            $record->google_calendar_link != $data['google_calendar_link'] ||
            $record->zoom_link != $data['zoom_link'];

        $record->update($data);

        if ($sendScheduleUpdate) {
            Notification::send($record->registeredUsers, new ScheduleUpdated($record));
        }

        return $record;
    }
}
