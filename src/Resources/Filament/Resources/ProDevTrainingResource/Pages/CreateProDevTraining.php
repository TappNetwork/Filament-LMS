<?php

namespace App\Filament\Resources\ProDevTrainingResource\Pages;

use App\Filament\Resources\ProDevTrainingResource;
use App\Models\ProDevTraining;
use App\Models\User;
use App\Notifications\ProDevTraining\NewTraining;
use Carbon\Carbon;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;
use Spatie\CalendarLinks\Link;

class CreateProDevTraining extends CreateRecord
{
    protected static string $resource = ProDevTrainingResource::class;

    /** @var \App\Models\ProDevTraining */
    public ?Model $record;

    protected function handleRecordCreation(array $data): Model
    {
        $description = 'Training Description: '.
            $data['description'].
            ' Meeting Link: '.
            $data['zoom_link'];

        $link = Link::create($data['name'], Carbon::parse($data['starts_at']), Carbon::parse($data['ends_at']))
            ->description($description);

        return static::getModel()::create([
            'name' => $data['name'],
            'description' => $data['description'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'speaker_name' => $data['speaker_name'],
            'speaker_org' => $data['speaker_org'],
            'zoom_link' => $data['zoom_link'],
            'filament_form_id' => $data['filament_form_id'],
            'google_calendar_link' => $link->google(),
            'ics_calendar_link' => $link->ics(),
        ]);
    }

    protected function afterCreate(): void
    {
        $training = ProDevTraining::findOrFail($this->record->id);

        Notification::send(User::all(), new NewTraining($training));
    }
}
