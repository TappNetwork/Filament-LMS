<?php

namespace Tapp\FilamentLms\Resources;

use Tapp\FilamentLms\Resources\StepResource\Pages;
use Tapp\FilamentLms\Resources\StepResource\RelationManagers;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Video;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Set;
use Illuminate\Support\Str;

class StepResource extends Resource
{
    protected static ?string $model = Step::class;

    protected static ?string $navigationIcon = 'heroicon-o-check-circle';

    protected static ?string $navigationGroup = 'LMS';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state) {
                        $set('slug', Str::slug($state));
                    })
                    ->required(),
                Forms\Components\TextInput::make('slug')
                    ->helperText('Used for urls.')
                    ->required(),
                Forms\Components\Select::make('lesson_id')
                    ->relationship(name: 'lesson', titleAttribute: 'name')
                    ->required(),
                Forms\Components\MorphToSelect::make('material')
                    ->types([
                        Forms\Components\MorphToSelect\Type::make(Video::class)
                        ->titleAttribute('name'),
                    ])
                    ->required(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lesson.course.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lesson.name')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSteps::route('/'),
            'create' => Pages\CreateStep::route('/create'),
            'edit' => Pages\EditStep::route('/{record}/edit'),
        ];
    }
}
