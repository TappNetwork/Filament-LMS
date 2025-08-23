<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Table;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                \Filament\Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(function ($record) {
                        $user = $this->getOwnerRecord();
                        if (is_callable([$user, 'getCourseProgress'])) {
                            $progress = $user->getCourseProgress($record);

                            return number_format($progress, 0).'%';
                        }

                        return 'N/A';
                    }),
            ])
            ->headerActions([
                AttachAction::make()->label('Add Course')->preloadRecordSelect(),
            ])
            ->actions([
                DetachAction::make()->label('Remove'),
            ]);
    }
}
