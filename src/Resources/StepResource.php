<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ViewField;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentLms\Concerns\HasLmsSlug;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Image;
use Tapp\FilamentLms\Models\Link;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;
use Tapp\FilamentLms\Models\Video;
use Tapp\FilamentLms\Resources\StepResource\Pages\CreateStep;
use Tapp\FilamentLms\Resources\StepResource\Pages\EditStep;
use Tapp\FilamentLms\Resources\StepResource\Pages\ListSteps;

class StepResource extends Resource
{
    use HasLmsSlug;

    protected static ?string $model = Step::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-check-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

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
                Placeholder::make('material_help')
                    ->label('')
                    ->content('**Step Material**: Select an existing material or create a new one. After selecting a material type, you can create a new material using the action button that appears.')
                    ->columnSpanFull(),
                MorphToSelect::make('material')
                    ->label('Step Material')
                    ->types([
                        Type::make(Video::class)
                            ->titleAttribute('name')
                            ->label('Video'),
                        Type::make(Document::class)
                            ->titleAttribute('name')
                            ->label('Document'),
                        Type::make(Link::class)
                            ->titleAttribute('name')
                            ->label('Link'),
                        Type::make(Image::class)
                            ->titleAttribute('name')
                            ->label('Image'),
                        Type::make(FilamentForm::class)
                            ->titleAttribute('name')
                            ->label('Form'),
                        Type::make(Test::class)
                            ->titleAttribute('name')
                            ->label('Test'),
                    ])
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            // Get the material type from the selected material
                            $materialType = get_class($state);
                            $set('material_type', $materialType);
                        }
                    }),
                Hidden::make('material_type'),
                MarkdownEditor::make('text')
                    ->label('Text Content')
                    ->placeholder('Enter step text content...')
                    ->columnSpanFull(),

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
            //
        ];
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
