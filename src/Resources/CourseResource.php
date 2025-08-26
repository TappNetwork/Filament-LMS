<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Course;
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

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation, $get) {
                        // Always update slug when name changes
                        $set('slug', Str::slug($state));

                        // Only auto-generate external_id on create or if it's empty
                        if ($operation === 'create' || empty($get('external_id'))) {
                            $set('external_id', Str::snake($state));
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
                    ->helperText('Image will be automatically cropped to a square.')
                    ->collection('courses')
                    ->image()
                    ->imageResizeMode('cover')
                    ->imageResizeTargetWidth('1080')
                    ->imageResizeTargetHeight('1080')
                    ->imageCropAspectRatio('1:1'),
                Textarea::make('description'),
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
                Checkbox::make('hidden'),

                /*
                     * TODO: Implement award layout and content
                     */
                // Forms\Components\Select::make('award_layout')
                //     ->relationship(name: 'award', titleAttribute: 'name')
                //     ->required(),
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
            ])
            ->filters([
                //
            ])
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
        return [
            LessonsRelationManager::make(),
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
