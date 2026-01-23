<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Forms\Components\MorphToSelectWithCreate;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Resources\StepResource\Pages\CreateStep;
use Tapp\FilamentLms\Resources\StepResource\Pages\EditStep;
use Tapp\FilamentLms\Resources\StepResource\Pages\ListSteps;

class StepResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Step::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

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

        return Step::getTenantRelationshipName();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('slug', Str::slug($state));
                    })
                    ->required(),
                TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->unique(ignoreRecord: true)
                    ->required(),
                Select::make('lesson_id')
                    ->relationship(name: 'lesson', titleAttribute: 'name')
                    ->preload()
                    ->required(),
                Checkbox::make('is_optional')
                    ->label('Optional Step')
                    ->helperText('Optional steps can be skipped without completing them. Users can proceed to the next step without finishing optional steps.'),
                MarkdownEditor::make('text')
                    ->label('Text Content')
                    ->placeholder('Enter step text content...')
                    ->helperText('This text will be displayed at the top of the step page for all users, before the step material. This is useful for providing instructions, context, or additional information about the step.')
                    ->columnSpanFull(),
                ...MorphToSelectWithCreate::make('material'),
                Checkbox::make('require_perfect_score')
                    ->label('Require Perfect Score to Proceed')
                    ->helperText('When enabled, users must answer all questions correctly (100%) before they can proceed to the next step. If disabled, users can proceed with any score.')
                    ->visible(function (Get $get, $livewire) {
                        // Check form state first (for create/edit)
                        $materialType = $get('material_type');
                        if ($materialType === 'test') {
                            return true;
                        }
                        // Check record when editing existing step
                        if (isset($livewire->record) && $livewire->record) {
                            return $livewire->record->material_type === 'test';
                        }

                        return false;
                    })
                    ->default(false)
                    ->live(),
                Select::make('retry_step_id')
                    ->label('Review Previous Step')
                    ->helperText('If the user does not get all questions correct, they will be shown a message with a link to this step to review before retrying the test.')
                    ->relationship(
                        name: 'retryStep',
                        titleAttribute: 'name',
                        modifyQueryUsing: function ($query, Get $get, $livewire) {
                            // Exclude the current step from the options
                            if (isset($livewire->record) && $livewire->record && $livewire->record->id) {
                                $query->where('id', '!=', $livewire->record->id);
                            }
                            // Only show steps from the same course
                            $lessonId = $get('lesson_id');
                            if ($lessonId) {
                                $query->whereHas('lesson', function ($q) use ($lessonId) {
                                    $lesson = \Tapp\FilamentLms\Models\Lesson::find($lessonId);
                                    if ($lesson) {
                                        $q->where('course_id', $lesson->course_id);
                                    }
                                });
                            }

                            return $query;
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->visible(function (Get $get, $livewire) {
                        // Only show if require_perfect_score is checked
                        $requirePerfect = $get('require_perfect_score');
                        if ($requirePerfect) {
                            return true;
                        }
                        // Check record when editing existing step
                        if (isset($livewire->record) && $livewire->record) {
                            return $livewire->record->require_perfect_score === true;
                        }

                        return false;
                    })
                    ->nullable(),

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
                TextColumn::make('lesson.course.name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('lesson.name')
                    ->searchable()
                    ->sortable(),
                BooleanColumn::make('is_optional')
                    ->label('Optional')
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSteps::route('/'),
            'create' => CreateStep::route('/create'),
            'edit' => EditStep::route('/{record}/edit'),
        ];
    }
}
