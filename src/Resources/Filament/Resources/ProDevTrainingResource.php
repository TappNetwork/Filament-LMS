<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProDevTrainingResource\Pages;
use App\Filament\Resources\ProDevTrainingResource\RelationManagers\UsersRelationManager;
use App\Models\ProDevTraining;
use App\Notifications\ProDevTraining\Announcement;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Notification;

class ProDevTrainingResource extends Resource
{
    protected static ?string $model = ProDevTraining::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    public static ?string $navigationGroup = 'Trainings';

    public static function getLabel(): string
    {
        return strval(__('models.pro_dev_training'));
    }

    public static function getPluralLabel(): string
    {
        return strval(__('models.pro_dev_training'));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->required(),
                Forms\Components\DateTimePicker::make('starts_at')
                    ->afterStateUpdated(function (?string $state, ?string $old, Set $set) {
                        $set('ends_at', Carbon::parse($state)->addHours(1)->format('Y-m-d\TH:i:s'));
                    })
                    ->live(debounce: 500)
                    ->seconds(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('ends_at')
                    ->rules([
                        'after:starts_at',
                    ])
                    ->seconds(false)
                    ->required(),
                Forms\Components\TextInput::make('speaker_name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('speaker_org')
                    ->label('Speaker Organization')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('zoom_link')
                    ->label('Meeting Link')
                    ->required(),
                Forms\Components\TextInput::make('google_calendar_link')
                    ->hiddenOn('create')
                    ->readOnly(),
                Forms\Components\TextInput::make('ics_calendar_link')
                    ->label('ICS Calendar Link')
                    ->hiddenOn('create')
                    ->readOnly(),
                Select::make('filament_form_id')
                    ->relationship('survey', 'name')
                    ->hidden(fn ($livewire) => $livewire instanceof EditRecord)
                    ->preload()
                    ->required()
                    ->searchable(),
                Placeholder::make('survey_display')
                    ->hint('Survey form cannot be updated after creation')
                    ->label('Survey')
                    ->content(function ($record) {
                        return $record?->survey?->name ? $record->survey->name : '';
                    })
                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('registered_users_count')
                    ->counts('registeredUsers')
                    ->sortable(),
                TextColumn::make('attended_users_count')
                    ->counts('attendedUsers')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('speaker_name')
                    ->searchable(),
                TextColumn::make('speaker_org')
                    ->searchable(),
                TextColumn::make('zoom_link')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at')
            ->filters([
                Filter::make('future_trainings')
                    ->query(fn (Builder $query): Builder => $query->whereDate('starts_at', '>=', now())),
                Filter::make('past_training')
                    ->query(fn (Builder $query): Builder => $query->whereDate('starts_at', '<', now())),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                ActionGroup::make([
                    Action::make('notify_registered')
                        ->form([
                            Textarea::make('message')
                                ->required(),
                        ])
                        ->action(function (array $data, ProDevTraining $record): void {
                            Notification::send($record->registeredUsers, new Announcement($data['message'], $record));
                        }),
                    Action::make('notify_attended')
                        ->form([
                            Textarea::make('message')
                                ->required(),
                        ])
                        ->action(function (array $data, ProDevTraining $record): void {
                            Notification::send($record->attendedUsers, new Announcement($data['message'], $record));
                        })
                        ->visible(fn (ProDevTraining $record): bool => $record->starts_at < now()),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProDevTrainings::route('/'),
            'create' => Pages\CreateProDevTraining::route('/create'),
            'edit' => Pages\EditProDevTraining::route('/{record}/edit'),
        ];
    }
}
