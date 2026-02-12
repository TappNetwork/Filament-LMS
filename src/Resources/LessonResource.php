<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Resources\LessonResource\Pages\CreateLesson;
use Tapp\FilamentLms\Resources\LessonResource\Pages\EditLesson;
use Tapp\FilamentLms\Resources\LessonResource\Pages\ListLessons;
use Tapp\FilamentLms\Resources\LessonResource\RelationManagers\StepsRelationManager;

class LessonResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Lesson::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

    /**
     * Disable Filament's automatic tenant scoping.
     * We use our own model-level global scope instead for better consistency
     * across both Resource queries and direct Eloquent queries (LMS pages).
     */
    public static function isScopedToTenant(): bool
    {
        return false;
    }

    /**
     * Get the tenant ownership relationship name.
     */
    public static function getTenantOwnershipRelationshipName(): string
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return 'tenant';
        }

        return Lesson::getTenantRelationshipName();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('slug', Str::slug($state));
                    })
                    ->maxLength(255),
                TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->required(),
                Select::make('course_id')
                    ->relationship(name: 'course', titleAttribute: 'name')
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
                    ->searchable()
                    ->sortable(),
                TextColumn::make('course.name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
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

    public static function getRelations(): array
    {
        return [
            StepsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLessons::route('/'),
            'create' => CreateLesson::route('/create'),
            'edit' => EditLesson::route('/{record}/edit'),
        ];
    }
}
