<?php

namespace App\Filament\Resources\TrainingResource\Pages;

use App\Filament\Resources\TrainingResource;
use App\Models\Training;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTraining extends EditRecord
{
    protected static string $resource = TrainingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ReplicateAction::make()
                ->form([
                    Forms\Components\TextInput::make('name')
                        ->maxLength(255)
                        ->required(),
                    Forms\Components\DateTimePicker::make('start_date')
                        ->seconds(false)
                        ->required(),
                ])
                ->action(function (array $data, Training $record, Actions\Action $action): void {
                    $replica = $record->replicate();
                    // change the dates to match the new start date
                    // @phpstan-ignore-next-line
                    $difference = round($replica->start_date->diffInDays($data['start_date']));
                    $replica->name = $data['name'];
                    $replica->start_date = $data['start_date'];
                    // @phpstan-ignore-next-line
                    $replica->end_date = $replica->end_date->addDays($difference);
                    $replica->save();

                    foreach ($record->trainingDays as $day) {
                        $replicaDay = $day->replicate();
                        // @phpstan-ignore-next-line
                        $replicaDay->starts_at = $day->starts_at->addDays($difference);
                        // @phpstan-ignore-next-line
                        $replicaDay->ends_at = $day->ends_at->addDays($difference);
                        $replicaDay->training_id = $replica->id;
                        $replicaDay->save();
                        $replicaDay->modules()->sync($day->modules()->pluck('modules.id'));
                    }

                    $action->success();

                    redirect()->route('filament.admin.resources.trainings.edit', $replica);
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title('Training replicated')
                        ->body('You are now editing the new training.'),
                ),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
