<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Jobs\GenerateLinkScreenshot;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Resources\LinkResource\Pages\CreateLink;
use Tapp\FilamentLms\Resources\LinkResource\Pages\EditLink;
use Tapp\FilamentLms\Resources\LinkResource\Pages\ListLinks;

class LinkResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Link::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paper-clip';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('url')
                    ->activeUrl()
                    ->required(),
                SpatieMediaLibraryFileUpload::make('preview')
                    ->collection('preview')
                    ->helperText('If not provided, the preview will be generated from the URL.')
                    ->image(),
                Actions::make([
                    Action::make('regeneratePreview')
                        ->icon('heroicon-o-arrow-path')
                        ->label('Regenerate Preview from Url')
                        ->visible(fn ($livewire) => $livewire->record && $livewire->record->exists)
                        ->action(function (Link $record) {
                            $record->clearMediaCollection('preview');
                            GenerateLinkScreenshot::dispatch($record);
                        }),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('url')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
            'index' => ListLinks::route('/'),
            'create' => CreateLink::route('/create'),
            'edit' => EditLink::route('/{record}/edit'),
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
