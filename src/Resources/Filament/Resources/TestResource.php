<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TestResource\Pages;
use App\Models\Test;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Table;

class TestResource extends Resource
{
    protected static ?string $model = Test::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getNavigationGroup(): ?string
    {
        return config('filament-form-builder.admin-panel-group-name');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
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
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('form.name')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
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
            ->actions([
                Tables\Actions\EditAction::make(),
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
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
        ];
    }
}
