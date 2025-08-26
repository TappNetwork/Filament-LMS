<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('progress')
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
            ->recordActions([
                DetachAction::make()->label('Remove'),
            ]);
    }
}
