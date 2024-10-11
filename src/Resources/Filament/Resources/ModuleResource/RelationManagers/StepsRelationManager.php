<?php

namespace App\Filament\Resources\ModuleResource\RelationManagers;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Form $form): Form
    {
        $module = $this->getOwnerRecord();

        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description'),
                Hidden::make('order')
                    ->default(function () use ($module) {
                        // @phpstan-ignore-next-line
                        return $module->steps->count();
                    }),
                Hidden::make('stepable_type')
                    ->default('App\Models\Module'),
                TextInput::make('link')
                    ->url(),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->multiple(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->reorderable('order')
            ->defaultSort('order')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
