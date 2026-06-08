<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Tapp\FilamentLms\Models\Course;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'Assigned Courses';

    protected static ?string $modelLabel = 'Course';

    protected static ?string $pluralModelLabel = 'Courses';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('external_id')
                    ->label('External ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                TextColumn::make('created_at')
                    ->label('Assigned At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns($this->getCourseSearchColumns())
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        $ownerRecord = $this->getOwnerRecord();
                        if (method_exists($ownerRecord, 'courses')) {
                            // @phpstan-ignore-next-line - courses() method is provided by FilamentLmsUser trait
                            $existingCourseIds = $ownerRecord->courses()->pluck('course_id');

                            return $query->whereNotIn('lms_courses.id', $existingCourseIds);
                        }

                        return $query;
                    })
                    ->schema(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    DetachAction::make(),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Get the searchable columns for the course model.
     * This method can be overridden or configured.
     *
     * @return list<string>
     */
    protected function getCourseSearchColumns(): array
    {
        $configColumns = config('filament-lms.course_search_columns');
        if ($configColumns && is_array($configColumns)) {
            return $configColumns;
        }

        $course = new Course;

        $columns = ['name'];

        if ($course->getConnection()->getSchemaBuilder()->hasColumn($course->getTable(), 'external_id')) {
            $columns[] = 'external_id';
        }

        return $columns;
    }
}
