<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Resources\TestResource\Pages\CreateTest;
use Tapp\FilamentLms\Resources\TestResource\Pages\EditTest;
use Tapp\FilamentLms\Resources\TestResource\Pages\ListTests;

class TestResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Test::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

    /**
     * Check if this resource should be scoped to a tenant.
     */
    public static function isScopedToTenant(): bool
    {
        return config('filament-lms.tenancy.enabled', false);
    }

    /**
     * Get the tenant ownership relationship name.
     */
    public static function getTenantOwnershipRelationshipName(): string
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return 'tenant';
        }

        return Test::getTenantRelationshipName();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Select::make('filament_form_id')
                    ->helperText('For tests, you may only select forms that are locked and no longer open to modification')
                    ->relationship(
                        name: 'form',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query) {
                            return $query->where('locked', true);
                        })
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('form.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                IconColumn::make('filament_form_user_id')
                    ->label('Rubric Created?')
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        return (bool) $record->filament_form_user_id;
                    })
                    ->boolean(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('create_rubric')
                    ->color('success')
                    ->action(function (Test $record) {
                        return redirect(route('filament.admin.pages.create-rubric', ['test' => $record]));
                    })
                    ->visible(function (Test $record) {
                        return ! $record->filament_form_user_id;
                    }),
                Action::make('view_rubric')
                    ->color('success')
                    ->action(function (Test $record) {
                        return redirect(route('filament.admin.pages.view-rubric', ['test' => $record]));
                    })
                    ->visible(function (Test $record) {
                        return $record->filament_form_user_id;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTests::route('/'),
            'create' => CreateTest::route('/create'),
            'edit' => EditTest::route('/{record}/edit'),
        ];
    }
}
