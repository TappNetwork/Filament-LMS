<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns($this->getUserSearchColumns())
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query->whereNotIn('users.id', $this->getOwnerRecord()->users()->pluck('user_id')))
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                    ]),
            ])
            ->actions([
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
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
