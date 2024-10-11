<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\ResourceResource\Pages;
use App\Infolists\Components\ResourcePreview;
use App\Models\Resource as ResourceModel;
use Archilex\ToggleIconColumn\Columns\ToggleIconColumn;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResourceResource extends Resource
{
    protected static ?string $model = ResourceModel::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static ?string $navigationLabel = 'Resource Library';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Name')
                    ->required(),
                Forms\Components\TextInput::make('external_link')
                    ->label('External Link')
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
                IconColumn::make('type'),
                TextColumn::make('categories.name')
                    ->color('warning')
                    ->badge(),
                TextColumn::make('tags.name')
                    ->badge(),
                ToggleIconColumn::make('is_favorite')
                    ->label('Favorite?')
                    ->onIcon('heroicon-s-star')
                    ->offIcon('heroicon-o-star')
                    ->updateStateUsing(fn (ResourceModel $record) => $record->toggleFavorite()),
            ])
            ->filters([
                TernaryFilter::make('favorite')
                    ->queries(
                        // @phpstan-ignore-next-line
                        true: fn (Builder $query) => $query->favorited(),
                        // @phpstan-ignore-next-line
                        false: fn (Builder $query) => $query->notFavorited(),
                        blank: fn (Builder $query) => $query,
                    ),
                SelectFilter::make('categories')
                    ->multiple()
                    ->relationship('categories', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('tags')
                    ->multiple()
                    ->relationship('tags', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Action::make('view')
                    ->url(function (ResourceModel $record) {
                        return '/resources/'.$record->id;
                    })->openUrlInNewTab(),
            ])
            ->bulkActions([]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(fn (ResourceModel $record) => $record->name)
                ->headerActions([
                    Infolists\Components\Actions\Action::make('favorite')
                        ->iconButton()
                        ->icon(fn (ResourceModel $record) => $record->isFavorite() ? 'heroicon-s-star' : 'heroicon-o-star')
                        ->action(fn (ResourceModel $record) => $record->toggleFavorite()),
                    Infolists\Components\Actions\Action::make('download')
                        ->iconButton()
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (ResourceModel $record) => $record->getFirstMedia('file'))
                        ->action(fn (ResourceModel $record) => $record->getFirstMedia('file')),
                    Infolists\Components\Actions\Action::make('share')
                        ->iconButton()
                        ->icon('heroicon-o-share')
                        ->action(function ($livewire) {
                            $livewire->js(
                                'window.navigator.clipboard.writeText(window.location.href);
                    $tooltip("'.__('Copied to clipboard').'", { timeout: 1500 });'
                            );
                        }),
                ])
                ->footerActions([
                    Infolists\Components\Actions\Action::make('download_resource')
                        ->visible(fn (ResourceModel $record) => $record->getFirstMedia('file'))
                        ->action(fn (ResourceModel $record) => $record->getFirstMedia('file')),
                ])
                ->schema([
                    ResourcePreview::make('file')
                        ->columnSpan(2)
                        ->label(''),
                    Infolists\Components\TextEntry::make('type'),
                    Infolists\Components\TextEntry::make('categories.name')
                        ->color('warning')
                        ->badge(),
                    Infolists\Components\TextEntry::make('tags.name')
                        ->badge(),
                    Infolists\Components\TextEntry::make('description')
                        ->columnSpan(2),
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
            'view' => Pages\ViewResource::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
