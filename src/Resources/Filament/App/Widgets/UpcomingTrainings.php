<?php

namespace App\Filament\App\Widgets;

use App\Models\LearningAccommodation;
use App\Models\Training;
use App\Models\TrainingUser;
use App\Tables\Columns\TightTextColumn;
use Carbon\Carbon;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingTrainings extends BaseWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                return Training::where('start_date', '>', now())
                    ->with([
                        'trainer',
                    ]);
            })
            ->columns([
                Stack::make([
                    \App\Tables\Columns\TitleColumn::make('name')
                        ->searchable(),
                    Stack::make([
                        TextColumn::make('start_date')
                            ->date('F j, Y, g:i a')
                            ->sortable(),
                        TextColumn::make('end_date')
                            ->dateTime('F j, Y, g:i a')
                            ->sortable(),
                        TightTextColumn::make('location')
                            ->searchable(),
                        TightTextColumn::make('trainer.name')
                            ->searchable(),
                    ]),
                    \App\Tables\Columns\DescriptionColumn::make('description')
                        ->searchable(),
                ])->space(2),
            ])
            ->actions($this->tableActions())
            ->contentGrid([
                'md' => 1,
            ]);
    }

    public function tableActions(): array
    {
        return [
            Action::make('cancel_registration')
                ->color('danger')
                ->visible(function (Training $record) {
                    return $record->users()
                        ->where('training_user.user_id', auth()->user()->id)
                        ->exists();
                })
                ->action(function (Training $record) {
                    TrainingUser::where('training_id', $record->id)
                        ->where('user_id', auth()->user()->id)
                        ->delete();
                }),
            Action::make('register')
                ->color('primary')
                ->form([
                    Select::make('learning_accommodations')
                        ->label('Do you need any learning accommodations')
                        ->multiple()
                        ->options(LearningAccommodation::query()->pluck('name', 'id')),
                    Textarea::make('dietary_restrictions')
                        ->label('Do you have any dietary restrictions?'),
                ])
                ->visible(function (Training $record) {
                    return
                        auth()->check() &&
                        $record->start_date > now() &&
                        Carbon::parse($record->start_date)->subDays(10) < now() &&
                        ! $record
                            ->users()
                            ->where('training_user.user_id', auth()->user()->id)
                            ->exists();
                })
                ->action(function (Training $record, array $data) {
                    $trainingUser = TrainingUser::create([
                        'training_id' => $record->id,
                        'user_id' => auth()->user()->id,
                        'dietary_restrictions' => $data['dietary_restrictions'],
                    ]);

                    $trainingUser->learningAccommodations()->attach($data['learning_accommodations']);
                }),
        ];
    }
}
