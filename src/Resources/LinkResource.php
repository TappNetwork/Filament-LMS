<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Resources\LinkResource\Pages;

class LinkResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Link::class;

    protected static ?string $navigationIcon = 'heroicon-o-paper-clip';

    protected static ?string $navigationGroup = 'LMS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->activeUrl()
                    ->required(),
                \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('preview')
                    ->collection('preview')
                    ->image()
                    ->disabled()
                    ->visible(fn ($livewire) => $livewire->record && $livewire->record->exists),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('regeneratePreview')
                        ->icon('heroicon-o-arrow-path')
                        ->label('Regenerate Preview')
                        ->visible(fn ($livewire) => $livewire->record && $livewire->record->exists)
                        ->action(function (Link $record) {
                            $record->clearMediaCollection('preview');
                            \Tapp\FilamentLms\Jobs\GenerateLinkScreenshot::dispatch($record);
                        }),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('preview')
                    ->getStateUsing(fn ($record) => $record->getFirstMediaUrl('preview'))
                    ->label('Preview')
                    ->square()
                    ->height(50),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('url')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListLinks::route('/'),
            'create' => Pages\CreateLink::route('/create'),
            'edit' => Pages\EditLink::route('/{record}/edit'),
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
