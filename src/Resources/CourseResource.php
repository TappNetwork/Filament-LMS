<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CreditCategory;
use Tapp\FilamentLms\RelationManagers\CourseUsersRelationManager;
use Tapp\FilamentLms\Resources\CourseResource\Pages\CreateCourse;
use Tapp\FilamentLms\Resources\CourseResource\Pages\EditCourse;
use Tapp\FilamentLms\Resources\CourseResource\Pages\ListCourses;
use Tapp\FilamentLms\Resources\CourseResource\RelationManagers\LessonsRelationManager;

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
        return $schema
            ->components([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation, $get) {
                        // Always update slug when name changes
                        $set('slug', Str::slug($state));

                        // Only auto-generate external_id on create or if it's empty.
                        // Use slug with underscore so acronyms stay together (e.g. DNS Cloudflare -> dns_cloudflare not d_n_s_cloudflare).
                        if ($operation === 'create' || empty($get('external_id'))) {
                            $set('external_id', Str::slug($state ?? '', '_'));
                        }
                    })
                    ->required(),
                TextInput::make('external_id')
                    ->label('External ID')
                    ->helperText('Used for external integrations like HubSpot. Updating this will cause a new property to be added to the integration.')
                    ->required()
                    ->rules([
                        'regex:/^[a-z][a-z0-9_]*$/',
                        'max:100',
                    ])
                    ->validationMessages([
                        'regex' => 'External ID must contain only lowercase letters, numbers, and underscores, and must start with a letter.',
                        'max' => 'External ID cannot exceed 100 characters.',
                    ]),
                TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->required(),
                SpatieMediaLibraryFileUpload::make('image')
                    ->helperText('Upload a course image.')
                    ->collection('courses')
                    ->image(),
                Textarea::make('description'),
                TextInput::make('required_test_percentage')
                    ->label('Required Average Test Score (percentage)')
                    ->helperText('User will not be able to complete the course until they have met this requirement.')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(0)
                    ->nullable(),
                Select::make('award')
                    ->options(config('filament-lms.awards'))
                    ->required()
                    ->hint(function ($record) {
                        // @phpstan-ignore-next-line
                        if ($record && $record->id) {
                            // @phpstan-ignore-next-line
                            $link = route('filament-lms::certificates.show', ['course' => $record->id, 'user' => auth()->id()]);

                            return new HtmlString("<a rel='noopener noreferrer' target='_blank' href='{$link}'>Click to Preview</a>");
                        }

                        return null;
                    })
                    ->helperText('Form must be saved before previewing.'),
                Checkbox::make('is_private')
                    ->label('Private Course')
                    ->helperText('Private courses are only visible to assigned users and LMS admins'),

                Repeater::make('courseCreditCategories')
                    ->relationship()
                    ->label(config('filament-lms.credits_repeater_label', 'Credits'))
                    ->schema([
                        Select::make('credit_category_id')
                            ->label('Category')
                            ->relationship('creditCategory', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                        TextInput::make('credits')
                            ->numeric()
                            ->required()
                            ->minValue(0.5)
                            ->step(0.5),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->addActionLabel(config('filament-lms.credits_add_action_label', 'Add credits'))
                    ->visible(fn (): bool => config('filament-lms.credits_enabled', false)),
            ]);
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
                    ->color(function (string $state): array {
                        static $hexMap = null;
                        $hexMap ??= CreditCategory::all()->mapWithKeys(fn ($cat) => [$cat->name => $cat->hexColor()])->all();
                        $categoryName = Str::before($state, ':');

                        return Color::hex($hexMap[$categoryName] ?? '#6b7280');
                    })
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
        return [
            LessonsRelationManager::make(),
            CourseUsersRelationManager::make(),
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
