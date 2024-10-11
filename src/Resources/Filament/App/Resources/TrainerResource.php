<?php

namespace App\Filament\App\Resources;

use App\Filament\App\Resources\TrainerResource\Pages;
use App\Models\User;
use Filament\Infolists;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TrainerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $modelLabel = 'Trainer';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('organization.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('counties.name')
                    ->label('Counties Served')
                    ->searchable()
                    ->sortable(),
                SpatieMediaLibraryImageColumn::make('profile_photo')
                    ->collection('profile_photo'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make(fn (User $record) => $record->id)
                ->description(fn (User $record) => $record->organization->name)
                // ->headerActions([
                    // Infolists\Components\Actions\Action::make('message')
                    //     ->icon('heroicon-o-chat-bubble-left')
                    //     ->action(fn () => null/*TODO*/),
                // ])
                ->schema([
                    SpatieMediaLibraryImageEntry::make('profile_photo')
                        ->collection('profile_photo')
                        ->columnSpan(2),
                    Infolists\Components\TextEntry::make('counties.name')
                        ->listWithLineBreaks(),
                    Infolists\Components\TextEntry::make('credentials')
                        ->listWithLineBreaks(),
                    Infolists\Components\TextEntry::make('education')
                        ->listWithLineBreaks(),
                    Infolists\Components\TextEntry::make('specializations')
                        ->listWithLineBreaks(),
                    Infolists\Components\TextEntry::make('bio')->columnSpan(2),
                ])->columns(2),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTrainers::route('/'),
            'view' => Pages\ViewTrainer::route('/{record}'),
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

    public static function getEloquentQuery(): Builder
    {
        return User::role('Trainer');
    }
}
