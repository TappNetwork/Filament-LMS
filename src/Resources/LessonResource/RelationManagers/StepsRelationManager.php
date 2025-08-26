<?php

namespace Tapp\FilamentLms\Resources\LessonResource\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Tapp\FilamentLms\Resources\StepResource\Pages\CreateStep;
use Tapp\FilamentLms\Resources\StepResource\Pages\EditStep;

class StepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->required(),
                Select::make('lesson_id')
                    ->relationship(name: 'lesson', titleAttribute: 'name')
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->required(),
                        TextInput::make('slug')
                            ->helperText('Used for urls.')
                            ->required(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->reorderable('order')
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('order'),
                TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->url(fn () => CreateStep::getUrl(['lesson_id' => $this->ownerRecord])),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn ($record) => EditStep::getUrl([$record])),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
