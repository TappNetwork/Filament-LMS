<?php

namespace Tapp\FilamentLms\Resources\LessonResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('slug')
                ->helperText('Used for urls.')
                ->required(),
                Forms\Components\Select::make('lesson_id')
                ->relationship(name: 'lesson', titleAttribute: 'name')
                ->required()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                        ->required(),
                        Forms\Components\TextInput::make('slug')
                        ->helperText('Used for urls.')
                        ->required(),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('order'),
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
