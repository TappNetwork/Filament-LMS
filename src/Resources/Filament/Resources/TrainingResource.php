<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TrainingResource\Pages;
use App\Filament\Resources\TrainingResource\RelationManagers\TrainingDaysRelationManager;
use App\Filament\Resources\TrainingResource\RelationManagers\UsersRelationManager;
use App\Filament\Resources\TrainingResource\RelationManagers\WaitListedUsersRelationManager;
use App\Models\Training;
use App\Models\TrainingUser;
use App\Models\User;
use BezhanSalleh\FilamentShield\Contracts\HasShieldPermissions;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TrainingResource extends Resource implements HasShieldPermissions
{
    protected static ?string $model = Training::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    public static ?string $navigationGroup = 'Trainings';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'update_own',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
            'restore',
            'restore_any',
            'replicate',
            'reorder',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('location')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->required(),
                Forms\Components\Select::make('certification_id')
                    ->label('Training Type')
                    ->relationship('certification', 'name')
                    ->hidden(fn ($livewire) => $livewire instanceof EditRecord)
                    ->required(),
                Placeholder::make('certification_display')
                    ->helperText('Training type cannot be updated after creation')
                    ->label('Training Type')
                    ->content(function ($record) {
                        return $record?->certification?->name ? $record->certification->name : '';
                    })
                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),
                Forms\Components\Select::make('trainer_id')
                    ->label('Trainer')
                    ->options(function () {
                        return User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Trainer'))->get()->pluck('full_name', 'id');
                    })
                    ->required(),
                Forms\Components\Select::make('backup_trainer_id')
                    ->label('Backup Trainer')
                    ->options(function () {
                        return User::whereHas('roles', fn ($q) => $q->where('roles.name', 'Trainer'))->get()->pluck('full_name', 'id');
                    })
                    ->required(),
                Placeholder::make('pre_test_display')
                    ->helperText('Pretest cannot be modified after a trainee has completed the pretest')
                    ->label('Certification')
                    ->content(function ($record) {
                        return $record?->preTest?->name ? $record->preTest->name : '';
                    })
                    ->visible(function (?Training $record) {
                        if (! $record) {
                            return false;
                        }

                        return TrainingUser::where('training_id', $record->id)
                            ->whereNotNull('pre_test_entry_id')
                            ->exists();
                    }),
                Forms\Components\Select::make('pre_test_id')
                    ->helperText('Pretest cannot be modified after a trainee has completed the pretest')
                    ->relationship(
                        name: 'preTest',
                        titleAttribute: 'name',
                    )
                    ->visible(function (?Training $record) {
                        if (! $record) {
                            return true;
                        }

                        return ! TrainingUser::where('training_id', $record->id)
                            ->whereNotNull('pre_test_entry_id')
                            ->exists();
                    })
                    ->required(),
                Placeholder::make('post_test_display')
                    ->helperText('Post test cannot be modified after a trainee has completed the post test')
                    ->label('Certification')
                    ->content(function ($record) {
                        return $record?->preTest?->name ? $record->preTest->name : '';
                    })
                    ->visible(function (?Training $record) {
                        if (! $record) {
                            return false;
                        }

                        return TrainingUser::where('training_id', $record->id)
                            ->whereNotNull('post_test_entry_id')
                            ->exists();
                    }),
                Forms\Components\Select::make('post_test_id')
                    ->helperText('Post Test cannot be modified after a trainee has completed the post test')
                    ->visible(function (?Training $record) {
                        if (! $record) {
                            return true;
                        }

                        return ! TrainingUser::where('training_id', $record->id)
                            ->whereNotNull('post_test_entry_id')
                            ->exists();
                    })
                    ->relationship(
                        name: 'postTest',
                        titleAttribute: 'name',
                    )
                    ->required(),
                Forms\Components\Select::make('trainer_eval_id')
                    ->relationship('trainerEvaluation', 'name')
                    ->required(),
                Forms\Components\DateTimePicker::make('start_date')
                    ->seconds(false)
                    ->required(),
                Forms\Components\DateTimePicker::make('end_date')
                    ->seconds(false)
                    ->required(),
                Placeholder::make('unscheduled_module_count')
                    ->helperText('Please assign any unscheduled modules to a training day')
                    ->label('Unscheduled Modules')
                    ->content(function ($record) {
                        return $record?->unscheduled_module_count;
                    })
                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),
                Placeholder::make('unassigned_modules')
                    ->label('Unassigned Modules')
                    ->content(function ($record) {
                        if (! $record) {
                            return '';
                        }

                        $certificationModules = $record->certification->modules()->pluck('name', 'id');

                        $assignedModules = $record->trainingDays()
                            ->join('module_training_day', 'training_days.id', '=', 'module_training_day.training_day_id')
                            ->join('modules', 'module_training_day.module_id', '=', 'modules.id')
                            ->select('modules.id', 'modules.name', 'module_training_day.is_partial')
                            ->selectRaw('COUNT(DISTINCT training_days.id) as training_day_count')
                            ->groupBy('modules.id', 'modules.name', 'module_training_day.is_partial')
                            ->get();

                        $unassignedModules = $certificationModules->diffKeys($assignedModules->pluck('is_partial', 'id'));

                        $partiallyAssignedModules = $assignedModules
                            ->where('is_partial', true)
                            ->where('training_day_count', 1)
                            ->pluck('name');

                        $result = $unassignedModules->merge($partiallyAssignedModules->map(fn ($name) => $name.' (partial)'));

                        return $result->isEmpty() ? 'All modules are assigned for this training' : $result->implode(', ');
                    })
                    ->visible(fn ($livewire) => $livewire instanceof EditRecord),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('trainer.name')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Registered Users')
                    ->counts('users')
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_users_count')
                    ->label('Approved Users')
                    ->counts('approvedUsers')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            TrainingDaysRelationManager::class,
            UsersRelationManager::class,
            WaitListedUsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainings::route('/'),
            'create' => Pages\CreateTraining::route('/create'),
            'edit' => Pages\EditTraining::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
