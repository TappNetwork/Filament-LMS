<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CreditCategory;
use Tapp\FilamentLms\RelationManagers\CourseUserGroupsRelationManager;
use Tapp\FilamentLms\RelationManagers\CourseUsersRelationManager;
use Tapp\FilamentLms\Resources\CourseResource\Pages\CreateCourse;
use Tapp\FilamentLms\Resources\CourseResource\Pages\EditCourse;
use Tapp\FilamentLms\Resources\CourseResource\Pages\ListCourses;
use Tapp\FilamentLms\Resources\CourseResource\RelationManagers\LessonsRelationManager;
use Tapp\FilamentLms\Resources\CourseResource\Schemas\CourseEditorForm;
use Tapp\FilamentLms\Support\CourseEditor;

class CourseResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Course::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

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

        return Course::getTenantRelationshipName();
    }

    public static function form(Schema $schema): Schema
    {
        if (CourseEditor::isBuilder()) {
            return $schema
                ->columns(1)
                ->components(CourseEditorForm::builderComponents());
        }

        return $schema->components(CourseEditorForm::legacyComponents());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_private')
                    ->label('Visibility')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'danger' : 'success')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Private (Manual Assignment)' : 'Public'),
                TextColumn::make('credits_summary')
                    ->label(config('filament-lms.credits_label', 'Credits'))
                    ->getStateUsing(function ($record): array {
                        $items = $record->courseCreditCategories
                            ->loadMissing('creditCategory')
                            ->groupBy('credit_category_id')
                            ->map(fn ($group) => [
                                'name' => $group->first()->creditCategory->name,
                                'credits' => (float) $group->sum('credits'),
                            ])
                            ->values();

                        return $items->map(fn ($item) => $item['name'].': '.number_format($item['credits'], 2))->values()->all();
                    })
                    ->placeholder('—')
                    ->badge()
                    ->color(CreditCategory::badgeColor(...))
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->visible(fn (): bool => config('filament-lms.credits_enabled', false)),
            ])
            ->filters([
                SelectFilter::make('credit_category_id')
                    ->label('Credit Category')
                    ->options(fn (): array => ['any' => 'Any credit category'] + CreditCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): void {
                        $value = $data['value'] ?? null;
                        if (blank($value)) {
                            return;
                        }
                        if ($value === 'any') {
                            $query->whereHas('courseCreditCategories');

                            return;
                        }
                        $query->whereHas('courseCreditCategories', fn (Builder $q): Builder => $q->where('credit_category_id', $value));
                    })
                    ->visible(fn (): bool => config('filament-lms.credits_enabled', false)),
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
        if (CourseEditor::isBuilder()) {
            return [];
        }

        return [
            LessonsRelationManager::make(),
            CourseUsersRelationManager::make(),
            CourseUserGroupsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCourses::route('/'),
            'create' => CreateCourse::route('/create'),
            'edit' => EditCourse::route('/{record}/edit'),
        ];
    }
}
