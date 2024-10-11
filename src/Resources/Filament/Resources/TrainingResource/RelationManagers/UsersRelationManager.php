<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Exports\Training\AttendanceExport;
use App\Exports\Training\RegistrationExport;
use App\Models\Training;
use App\Models\TrainingUser;
use App\Models\User;
use App\Notifications\Training\DemographicSurveyAvailable;
use App\Notifications\Training\PhotoVideoConsentAvailable;
use App\Notifications\Training\PreTestReminder;
use App\Notifications\Training\TrainerEvaluationReady;
use App\Notifications\TrainingUser\TrainingRegistrationRejected;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Actions\Action as InfolistAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentFormBuilder\Exports\FilamentFormUsersExport;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

/**
 * @method Training getOwnerRecord()
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'activeUsers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                IconColumn::make('is_ready_to_begin_training')
                    ->getStateUsing(function ($record) {
                        return $record->demographic_survey && $record->photo_video_consent_at && $record->pre_test_entry_id;
                    })
                    ->boolean(),
                IconColumn::make('is_certified')
                    ->label('Training Complete?')
                    ->getStateUsing(function ($record) {
                        return (bool) $record->certifications->contains('id', $this->getOwnerRecord()->certification_id);
                    })
                    ->boolean(),
                IconColumn::make('trainer_eval_id')
                    ->label('Trainer Evaluation Complete')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return (bool) $record->trainer_eval_id;
                    })
                    ->boolean(),
                TextColumn::make('pre_test_grade')
                    ->action(
                        Action::make('show_graded_pretest')
                            ->action(function (User $record) {
                                $test = $this->getOwnerRecord()->preTest;

                                redirect(
                                    route('filament.admin.pages.show-graded-test', [
                                        'test' => $test->id,
                                        // @phpstan-ignore-next-line
                                        'entry' => $record->pre_test_entry_id,
                                    ])
                                );
                            })
                            ->visible(function (User $record) {
                                // @phpstan-ignore-next-line
                                return $record->pre_test_entry_id;
                            })
                    ),
                TextColumn::make('post_test_grade')
                    ->action(
                        Action::make('show_graded_post_test')
                            ->action(function (User $record) {
                                $test = $this->getOwnerRecord()->postTest;

                                redirect(
                                    route('filament.admin.pages.show-graded-test', [
                                        'test' => $test->id,
                                        // @phpstan-ignore-next-line
                                        'entry' => $record->post_test_entry_id,
                                    ])
                                );
                            })
                            ->visible(function (User $record) {
                                // @phpstan-ignore-next-line
                                return $record->post_test_entry_id;
                            })
                    ),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('export_registration')
                    ->action(function () {
                        $training = $this->getOwnerRecord();

                        return Excel::download(
                            new RegistrationExport($training),
                            urlencode($this->getOwnerRecord()->name).'_registration_export'.now()->format('Y-m-dhis').'.csv'
                        );
                    })
                    ->icon('heroicon-o-user-group'),
            ])
            ->actions([
                Action::make('View Information')
                    ->infoList([
                        Section::make(function (User $model) {
                            return $model->name;
                        })
                            ->footerActions([
                                InfolistAction::make('send_pretest_reminder')
                                    ->label('Send pretest reminder')
                                    ->icon('heroicon-m-clipboard')
                                    ->hidden(function (User $record) {
                                        // @phpstan-ignore-next-line
                                        return (bool) $record->pre_test_entry_id;
                                    })
                                    ->action(function (User $record) {
                                        $record->notify(new PreTestReminder($this->getOwnerRecord()));
                                    }),
                                InfolistAction::make('send_consent_reminder')
                                    ->label('Send photo/video Consent reminder')
                                    ->icon('heroicon-m-clipboard')
                                    ->hidden(function (User $record) {
                                        // @phpstan-ignore-next-line
                                        return (bool) $record->photo_video_consent_at;
                                    })
                                    ->action(function (User $record) {
                                        $record->notify(new PhotoVideoConsentAvailable($this->getOwnerRecord()));
                                    }),
                                InfolistAction::make('send_demographic_survey_reminder')
                                    ->label('Send demographic survey reminder')
                                    ->icon('heroicon-m-clipboard')
                                    ->hidden(function (User $record) {
                                        // @phpstan-ignore-next-line
                                        return (bool) $record->demographic_survey;
                                    })
                                    ->action(function (User $record) {
                                        $record->notify(new DemographicSurveyAvailable($this->getOwnerRecord()));
                                    }),
                            ])
                            ->schema([
                                IconEntry::make('is_ready_to_begin_training')
                                    ->getStateUsing(function ($record) {
                                        return $record->demographic_survey && $record->photo_video_consent_at && $record->pre_test_entry_id;
                                    })
                                    ->boolean(),
                                TextEntry::make('pre_test_grade'),
                                TextEntry::make('post_test_grade'),
                                IconEntry::make('photo_video_consent_at')
                                    ->label('Photo/Video consent form completed')
                                    ->boolean(),
                                KeyValueEntry::make('demographic_survey')
                                    ->getStateUsing(function (User $record) {
                                        // @phpstan-ignore-next-line
                                        return json_decode($record->demographic_survey);
                                    }),
                            ]),
                    ]),
                Action::make('approve_registration')
                    ->color('primary')
                    ->visible(fn (User $record) => TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                        ->where('user_id', $record->id)
                        ->whereNull('approved_at')
                        ->exists()
                    )
                    ->action(function (User $record) {
                        TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->update([
                                'approved_at' => now(),
                            ]);

                        $record->dispatchPreTrainingNotifications($this->getOwnerRecord());
                    }),
                Action::make('reject_registration')
                    ->color('danger')
                    ->visible(fn (User $record) => ! TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                        ->where('user_id', $record->id)
                        ->whereNull('approved_at')
                        ->exists()
                    )
                    ->action(function (User $record) {
                        TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->update([
                                'approved_at' => null,
                            ]);

                        $record->notify(new TrainingRegistrationRejected($this->getOwnerRecord()));
                    }),
                Action::make('move_to_wait_list')
                    ->label('Add user to wait list')
                    ->color('danger')
                    ->visible(fn (User $record) => TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                        ->where('user_id', $record->id)
                        ->whereNull('approved_at')
                        ->whereNull('wait_listed_at')
                        ->exists()
                    )
                    ->action(function (User $record) {
                        TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->firstOrFail()
                            ->addToWaitList();
                    }),
                Action::make('grant_certification')
                    ->label('Mark Complete')
                    ->color('success')
                    ->action(function (User $record) {
                        $record->awardCertification($this->getOwnerRecord());
                    })
                    ->visible(fn (User $record) => TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                        ->where('user_id', $record->id)
                        ->firstOrFail()
                        ->is_certification_pending
                    )
                    ->visible(function (User $record) {
                        $tU = TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->firstOrFail();

                        return $tU->is_certification_pending && $tU->post_test_entry_id;
                    })
                    ->requiresConfirmation(),
                Action::make('send_trainer_evaluation_reminder')
                    ->color('success')
                    ->action(function (User $record) {
                        $record->notify(new TrainerEvaluationReady($this->getOwnerRecord()));
                    })
                    ->visible(fn (User $record) =>
                        // @phpstan-ignore-next-line
                        ! $record->trainer_eval_id &&
                        $record->certifications->contains('id', $this->getOwnerRecord()->certification_id)
                    )
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                BulkAction::make('export_trainer_evaluations')
                    ->label('Export Trainer Evaluations')
                    ->action(fn (Collection $records) => Excel::download(
                        new FilamentFormUsersExport(FilamentFormUser::whereIn('id', $records->pluck('trainer_eval_id'))->get()),
                        urlencode($this->getOwnerRecord()->name).'_trainer_evaluations_export'.now()->format('Y-m-dhis').'.csv'
                    ))
                    ->icon('heroicon-o-document-chart-bar')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('export_pretests')
                    ->label('Export Pretests')
                    ->action(fn (Collection $records) => Excel::download(
                        new FilamentFormUsersExport(FilamentFormUser::whereIn('id', $records->pluck('pre_test_entry_id'))->get()),
                        urlencode($this->getOwnerRecord()->name).'_pre_test_export'.now()->format('Y-m-dhis').'.csv'
                    ))
                    ->icon('heroicon-o-document-chart-bar')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('export_post_tests')
                    ->label('Export Post Tests')
                    ->action(fn (Collection $records) => Excel::download(
                        new FilamentFormUsersExport(FilamentFormUser::whereIn('id', $records->pluck('post_test_entry_id'))->get()),
                        urlencode($this->getOwnerRecord()->name).'_post_test_export'.now()->format('Y-m-dhis').'.csv'
                    ))
                    ->icon('heroicon-o-document-chart-bar')
                    ->deselectRecordsAfterCompletion(),
                BulkAction::make('export_attendance')
                    ->label('Export Attendance')
                    ->action(fn (Collection $records) => Excel::download(
                        new AttendanceExport($records),
                        urlencode($this->getOwnerRecord()->name).'_attendance_export'.now()->format('Y-m-dhis').'.csv'
                    ))
                    ->icon('heroicon-o-user-group')
                    ->deselectRecordsAfterCompletion(),
            ]);
    }
}
