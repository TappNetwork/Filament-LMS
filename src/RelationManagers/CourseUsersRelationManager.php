<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CourseUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Assigned Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->label('User')
                    ->relationship('users', 'name')
                    ->searchable($this->getUserSearchColumns())
                    ->preload()
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('lms_course_user.created_at', $direction);
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->checkIfRecordIsSelectableUsing(fn (Model $record): bool => $this->hasManualAssignment($record))
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns($this->getUserSearchColumns())
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        $ownerRecord = $this->getOwnerRecord();
                        if (method_exists($ownerRecord, 'users')) {
                            // @phpstan-ignore-next-line - users() method is provided by Course model relationship
                            $existingUserIds = $ownerRecord->users()->pluck('user_id');

                            return $query->whereNotIn('users.id', $existingUserIds);
                        }

                        return $query;
                    })
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        Forms\Components\Hidden::make('is_explicitly_assigned')
                            ->default(true),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    DetachAction::make()
                        ->visible(fn (Model $record): bool => $this->hasManualAssignment($record)),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function hasManualAssignment(Model $record): bool
    {
        if (! $record->relationLoaded('pivot')) {
            return false;
        }

        $pivot = $record->getRelation('pivot');

        if (! $pivot instanceof Pivot) {
            return false;
        }

        return (int) $pivot->getAttribute('is_explicitly_assigned') === 1;
    }

    /**
     * Get the searchable columns for the user model.
     * This method can be overridden or configured.
     */
    protected function getUserSearchColumns(): array
    {
        // First, try to get from configuration
        $configColumns = config('filament-lms.user_search_columns');
        if ($configColumns && is_array($configColumns)) {
            return $configColumns;
        }

        // Fallback to database detection
        $userModel = config('filament-lms.user_model');
        $user = new $userModel;

        // Check if the model has a name column
        if ($user->getConnection()->getSchemaBuilder()->hasColumn($user->getTable(), 'name')) {
            return ['name', 'email'];
        }

        // Check for first_name and last_name columns
        if ($user->getConnection()->getSchemaBuilder()->hasColumn($user->getTable(), 'first_name') &&
            $user->getConnection()->getSchemaBuilder()->hasColumn($user->getTable(), 'last_name')) {
            return ['first_name', 'last_name', 'email'];
        }

        // Fallback to email only
        return ['email'];
    }
}
