<?php

namespace Tapp\FilamentLms\Resources;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Form;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Resources\CourseResource\Pages;
use Tapp\FilamentLms\Resources\CourseResource\RelationManagers;

class CourseResource extends Resource
{
    protected static ?string $model = Course::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationGroup = 'LMS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state, string $operation) {
                        if ($operation === 'create') {
                            $set('external_id', Str::snake($state));
                        }
                        $set('slug', Str::slug($state));
                    })
                    ->required(),
                Forms\Components\TextInput::make('external_id')
                    ->disabledOn('edit')
                    ->label('External ID')
                    ->helperText('Cannot be changed after creation. Used for external integrations like HubSpot.')
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->required(),
                FileUpload::make('image')
                    ->helperText('Image will be automatically cropped to a square.')
                    ->disk('public')
                    ->directory('courses')
                    ->image()
                    ->imageResizeMode('cover')
                    ->imageResizeTargetWidth('1080')
                    ->imageResizeTargetHeight('1080')
                    ->imageCropAspectRatio('1:1'),
                Forms\Components\Textarea::make('description'),
                Forms\Components\Select::make('award')
                    ->options(config('filament-lms.awards'))
                    ->required()
                    ->hint(function ($record) {
                        if ($record?->id) {
                            $link = route('filament-lms::certificates.show', ['course' => $record->id, 'user' => auth()->id()]);

                            return new HtmlString("<a rel='noopener noreferrer' target='_blank' href='{$link}'>Click to Preview</a>");
                        }

                        return null;
                    })
                    ->helperText('Form must be saved before previewing.'),
                Forms\Components\Checkbox::make('hidden'),

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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LessonsRelationManager::make(),
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCourses::route('/'),
            'create' => Pages\CreateCourse::route('/create'),
            'edit' => Pages\EditCourse::route('/{record}/edit'),
        ];
    }
}
