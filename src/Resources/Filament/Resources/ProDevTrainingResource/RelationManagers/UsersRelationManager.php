<?php

namespace App\Filament\Resources\ProDevTrainingResource\RelationManagers;

use App\Models\ProDevTraining;
use App\Models\ProDevTrainingUser;
use App\Models\User;
use App\Notifications\ProDevTraining\PostSurveyReady;
use App\Notifications\ProDevTraining\PostSurveyReminder;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentFormBuilder\Exports\FilamentFormUsersExport;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

/**
 * @method ProDevTraining getOwnerRecord()
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'registeredUsers';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('attended')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return (bool) $record->attended;
                    })
                    ->boolean(),
                IconColumn::make('filament_form_user_id')
                    ->label('Post Survey Complete')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return (bool) $record->filament_form_user_id;
                    })
                    ->boolean(),
            ])
            ->actions([
                Action::make('mark_attended')
                    ->visible(fn () => $this->getOwnerRecord()->starts_at <= now())
                    ->action(function (User $record) {
                        $record->registeredProDevTrainings()
                            ->updateExistingPivot($this->getOwnerRecord()->id, ['attended' => now()]);

                        if ($this->getOwnerRecord()->filament_form_id) {
                            $record->notify(new PostSurveyReady($this->getOwnerRecord()));
                        }
                    }),
                Action::make('mark_absent')
                    ->visible(fn () => $this->getOwnerRecord()->starts_at <= now())
                    ->action(function (User $record) {
                        $record->registeredProDevTrainings()
                            ->updateExistingPivot($this->getOwnerRecord()->id, ['attended' => null]);
                    }),
                Action::make('send_survey_reminder')
                    ->visible(fn (User $record) => $this->getOwnerRecord()->starts_at <= now() &&
                        $this->getOwnerRecord()->filament_form_id &&
                        ProDevTrainingUser::where('pro_dev_training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->whereNull('filament_form_user_id')
                            ->exists()
                    )
                    ->action(function (User $record) {
                        $record->notify(new PostSurveyReminder(($this->getOwnerRecord())));
                    }),
            ])
            ->bulkActions([
                BulkAction::make('mark_attended')
                    ->visible(fn () => $this->getOwnerRecord()->starts_at <= now() && $this->getOwnerRecord()->filament_form_id)
                    ->action(function (Collection $records) {
                        /** @var \App\Models\User $user */
                        foreach ($records as $user) {
                            $user->registeredProDevTrainings()
                                ->updateExistingPivot($this->getOwnerRecord()->id, ['attended' => now()]);

                            if ($this->getOwnerRecord()->filament_form_id) {
                                $user->notify(new PostSurveyReady($this->getOwnerRecord()));
                            }
                        }
                    }),
                BulkAction::make('mark_absent')
                    ->visible(fn () => $this->getOwnerRecord()->starts_at <= now())
                    ->action(function (Collection $records) {
                        /** @var \App\Models\User $user */
                        foreach ($records as $user) {
                            $user->registeredProDevTrainings()
                                ->updateExistingPivot($this->getOwnerRecord()->id, ['attended' => null]);
                        }
                    }),
                BulkAction::make('send_survey_reminder')
                    ->visible(fn () => $this->getOwnerRecord()->starts_at <= now())
                    ->action(function (Collection $records) {
                        $proDevTrainingUsers = ProDevTrainingUser::where('pro_dev_training_id', $this->getOwnerRecord()->id)
                            ->whereIn('user_id', $records->pluck('id'))
                            ->whereNull('filament_form_user_id')
                            ->with('user')
                            ->get();

                        foreach ($proDevTrainingUsers as $proDevTrainingUser) {
                            $proDevTrainingUser->user->notify(new PostSurveyReminder(($this->getOwnerRecord())));
                        }
                    }),
                BulkAction::make('Export Selected')
                    ->label('Export Post Training Surveys')
                    ->action(function (Collection $records) {
                        if (! $records->contains(fn (User $record) => $record->filament_form_user_id)) {

                            Notification::make()
                                ->title('Export failed')
                                ->body('None of the selected users have completed the post training survey')
                                ->danger() // Can also be warning(), info(), or danger()
                                ->send();

                            return;
                        }

                        return Excel::download(
                            new FilamentFormUsersExport(FilamentFormUser::whereIn('id', $records->pluck('filament_form_user_id'))->get()),
                            urlencode($this->getOwnerRecord()->name).'_survey_export'.now()->format('Y-m-dhis').'.csv');
                    })
                    ->icon('heroicon-o-document-chart-bar')
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated(['all']);
    }
}
