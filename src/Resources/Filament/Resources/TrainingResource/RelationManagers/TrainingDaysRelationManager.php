<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Models\Module;
use App\Models\ModuleTrainingDay;
use App\Models\TrainingDay;
use Carbon\Carbon;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainingDaysRelationManager extends RelationManager
{
    protected static string $relationship = 'trainingDays';

    public array $newTrainingData = [];

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description'),
                DateTimePicker::make('starts_at')
                    ->afterStateUpdated(function (?string $state, ?string $old, Set $set) {
                        $set('ends_at', Carbon::parse($state)->addHours(4)->format('Y-m-d\TH:i:s'));
                    })
                    ->live(debounce: 500)
                    ->default(function () {
                        return $this->ownerRecord->start_date;
                    })
                    ->required()
                    ->minDate(fn () => $this->ownerRecord->start_date)
                    ->maxDate(fn () => $this->ownerRecord->end_date)
                    ->seconds(false),
                DateTimePicker::make('ends_at')
                    ->required()
                    ->rules([
                        'after:starts_at',
                    ])
                    ->default(function () {
                        return $this->ownerRecord->start_date;
                    })
                    ->minDate(fn () => $this->ownerRecord->start_date)
                    ->maxDate(fn () => $this->ownerRecord->end_date)
                    ->seconds(false),
                TextInput::make('meeting_location')
                    ->required(),
                Repeater::make('moduleTrainingDays')
                    ->relationship()
                    ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                        $data['is_partial'] = $data['is_partial'] ?? false;
                        unset($data['is_partial_hidden']);

                        return $data;
                    })
                    ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                        $data['is_partial'] = $data['is_partial'] ?? false;
                        unset($data['is_partial_hidden']);

                        return $data;
                    })
                    ->schema([
                        Select::make('module_id')
                            ->relationship(
                                name: 'module',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, ?Model $record, $get) {
                                    /** @var ?\App\Models\Training $training */
                                    $training = $this->ownerRecord;

                                    /** @var ?\App\Models\TrainingDay $record */
                                    $trainingDayId = $record?->id;

                                    $selectedModules = collect($get('../../..')['newTrainingData'])
                                        ->pluck('module_id')
                                        ->filter()
                                        ->toArray();

                                    // Include the current module_id if it exists
                                    // @phpstan-ignore-next-line
                                    $currentModuleId = $record?->module_id;
                                    if ($currentModuleId) {
                                        $selectedModules = array_diff($selectedModules, [$currentModuleId]);
                                    }

                                    // Get modules marked as partial only once for this training
                                    $partialOnceModules = ModuleTrainingDay::query()
                                        ->select('module_id')
                                        ->join('training_days', 'training_days.id', '=', 'module_training_day.training_day_id')
                                        ->where('training_days.training_id', $training->id)
                                        ->where('is_partial', true)
                                        ->groupBy('module_id')
                                        ->havingRaw('COUNT(*) = 1')
                                        ->pluck('module_id')
                                        ->toArray();

                                    return Module::whereHas('certifications', function ($query) use ($training) {
                                        $query->where('certifications.id', $training->certification_id);
                                    })
                                        ->where(function ($query) use ($training, $trainingDayId, $currentModuleId, $partialOnceModules) {
                                            $query->whereDoesntHave('trainingDays', function ($query) use ($training, $trainingDayId) {
                                                $query->when($trainingDayId, fn ($q) => $q->whereNot('training_days.id', $trainingDayId))
                                                    ->whereHas('training', function ($query) use ($training) {
                                                        $query->where('id', $training->id);
                                                    })
                                                    ->whereHas('moduleTrainingDays', function ($query) {
                                                        $query->where('is_partial', false);
                                                    });
                                            })
                                                ->orWhereIn('id', $partialOnceModules)
                                                ->orWhere('id', $currentModuleId); // Include the current module
                                        })
                                        ->whereNotIn('id', $selectedModules);
                                }
                            )
                            ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                /** @var \App\Models\Training $training */
                                $training = $this->ownerRecord;

                                $isPartiallyUsed = ModuleTrainingDay::query()
                                    ->whereHas('trainingDay', function ($query) use ($training) {
                                        $query->where('training_id', $training->id);
                                    })
                                    ->where('module_id', $state)
                                    ->where('is_partial', true)
                                    ->exists();

                                $set('is_partial', $isPartiallyUsed);
                                $set('is_partial_hidden', $isPartiallyUsed); // Set the hidden field value
                            })
                            ->reactive()
                            ->live()
                            ->preload()
                            ->searchable(),
                        Checkbox::make('is_partial')
                            ->label('Is this module split between training days?')
                            ->disabled(function (callable $get) {
                                $moduleId = $get('module_id');

                                /** @var \App\Models\Training $training */
                                $training = $this->ownerRecord;

                                return ModuleTrainingDay::query()
                                    ->whereHas('trainingDay', function ($query) use ($training) {
                                        $query->where('training_id', $training->id);
                                    })
                                    ->where('module_id', $moduleId)
                                    ->where('is_partial', true)
                                    ->exists();
                            })
                            ->dehydrated(true) // Change this to true
                            ->hint(function (callable $get) {
                                $moduleId = $get('module_id');
                                /** @var \App\Models\Training $training */
                                $training = $this->ownerRecord;

                                $isPartiallyUsed = ModuleTrainingDay::query()
                                    ->whereHas('trainingDay', function ($query) use ($training) {
                                        $query->where('training_id', $training->id);
                                    })
                                    ->where('module_id', $moduleId)
                                    ->where('is_partial', true)
                                    ->exists();

                                return $isPartiallyUsed
                                    ? 'This module has been partially selected in another training day.'
                                    : null;
                            })
                            ->columnSpanFull(),
                    ])
                    ->statePath('newTrainingData'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('starts_at'),
                Tables\Columns\TextColumn::make('ends_at'),
                IconColumn::make('closed_out')
                    ->boolean(),
                IconColumn::make('attendance_confirmed_at')
                    ->label('Attendance Confirmed')
                    ->boolean(),
            ])
            ->defaultSort('starts_at')
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make('edit')
                    ->visible(function (TrainingDay $record) {
                        return ! $record->closed_out;
                    }),
                Tables\Actions\DeleteAction::make()
                    ->visible(function (TrainingDay $record) {
                        return ! $record->closed_out;
                    }),
                Action::make('Take Attendance')
                    ->color('success')
                    ->modalHeading(function (TrainingDay $record) {
                        return 'Take Attendance for '.$record->name;
                    })
                    ->modalContent(fn (TrainingDay $record): View => view(
                        'training.training-day-attendance',
                        ['trainingDay' => $record]
                    ))
                    // @phpstan-ignore-next-line
                    ->modalSubmitAction(''),
                Action::make('close_out')
                    ->visible(function (TrainingDay $record) {
                        return ! $record->closed_out;
                    })
                    ->modalHeading(function (TrainingDay $record) {
                        return 'Close out '.$record->name.'? Closing out this training day will lock all modules associated with this training day and will make all other fields read only. Only an admin can undo this change.';
                    })
                    // !!! why are the following two methods not working?
                    ->modalDescription('Closing out this training day will lock all modules associated with this training day and will make all other fields read only. Only an admin can undo this change.')
                    ->modalSubmitActionLabel('Yes, close out this training day')
                    ->requiresConfirmation()
                    ->action(function (TrainingDay $record) {
                        $record->update([
                            'closed_out' => now(),
                        ]);
                    }),
                Action::make('undo_close_out')
                    ->visible(function (TrainingDay $record) {
                        return auth()->user()->hasRole('Admin') && $record->closed_out;
                    })
                    ->action(function (TrainingDay $record) {
                        $record->update([
                            'closed_out' => null,
                        ]);
                    }),
                Action::make('view_modules')
                    ->url(fn (TrainingDay $record): string => route('filament.app.pages.list-modules', ['training_day' => $record->id])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
