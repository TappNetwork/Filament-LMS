<?php

namespace App\Filament\Resources;

use App\Enums\ResourceType;
use App\Filament\Resources\ResourceResource\Pages;
use App\Models\Resource as ResourceModel;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ResourceResource extends Resource
{
    protected static ?string $model = ResourceModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static ?string $navigationGroup = 'Resource Library';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Select::make('type')
                    ->options(ResourceType::class)
                    ->default('image')
                    ->required()
                    ->live(),
                Forms\Components\TextInput::make('external_link')
                    ->visible(fn (Get $get): bool => $get('type') == 'external_link')
                    ->label('External Link')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('file')
                    ->visible(fn (Get $get): bool => $get('type') && $get('type') != 'external_link')
                    ->required(),
                Forms\Components\Select::make('tags')
                    ->preload()
                    ->multiple()
                    ->relationship(titleAttribute: 'name'),
                Forms\Components\Select::make('categories')
                    ->preload()
                    ->multiple()
                    ->relationship(titleAttribute: 'name'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('type'),
                TextColumn::make('categories.name')
                    ->color('warning')
                    ->badge(),
                TextColumn::make('tags.name')
                    ->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                ViewAction::make()
                    ->url(function (ResourceModel $record) {
                        return $record->type == 'external_link'
                            ? $record->external_link
                            : route('filament.app.resources.resources.view', $record);
                    })
                    ->openUrlInNewTab(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListResources::route('/'),
            'create' => Pages\CreateResource::route('/create'),
            'edit' => Pages\EditResource::route('/{record}/edit'),
        ];
    }
}
