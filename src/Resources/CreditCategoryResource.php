<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\CreditCategory;
use Tapp\FilamentLms\Resources\CreditCategoryResource\Pages\CreateCreditCategory;
use Tapp\FilamentLms\Resources\CreditCategoryResource\Pages\EditCreditCategory;
use Tapp\FilamentLms\Resources\CreditCategoryResource\Pages\ListCreditCategories;

class CreditCategoryResource extends Resource
{
    protected static ?string $model = CreditCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

    public static function shouldRegisterNavigation(): bool
    {
        return config('filament-lms.credits_enabled', false);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Textarea::make('description'),
                Select::make('color')
                    ->options(
                        collect(CreditCategory::COLORS)->mapWithKeys(
                            fn (string $hex, string $name): array => [$name => ucfirst($name)]
                        )->toArray()
                    )
                    ->default(fn (): string => CreditCategory::nextAvailableColor())
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('color')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->color(fn (CreditCategory $record): array => Color::hex($record->hexColor())),
                TextColumn::make('course_credit_categories_count')
                    ->counts('courseCreditCategories')
                    ->label('Courses')
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCreditCategories::route('/'),
            'create' => CreateCreditCategory::route('/create'),
            'edit' => EditCreditCategory::route('/{record}/edit'),
        ];
    }
}
