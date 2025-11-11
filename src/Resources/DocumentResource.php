<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Resources\DocumentResource\Pages\CreateDocument;
use Tapp\FilamentLms\Resources\DocumentResource\Pages\EditDocument;
use Tapp\FilamentLms\Resources\DocumentResource\Pages\ListDocuments;

class DocumentResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Document::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document';

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

        return Document::getTenantRelationshipName();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('file')
                    ->required(),
                TextInput::make('media_name')
                    ->label('File Display Name')
                    ->helperText('Custom name for the uploaded file (optional). Leave empty to use original filename.')
                    ->visible(fn ($livewire) => $livewire->record && $livewire->record->exists)
                    ->dehydrated(false),
                SpatieMediaLibraryFileUpload::make('preview')
                    ->collection('preview')
                    ->label('Custom Preview Image (optional)')
                    ->image()
                    ->maxFiles(1)
                    ->helperText('If set, this image will be used as the document preview.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type'),
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
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'edit' => EditDocument::route('/{record}/edit'),
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
