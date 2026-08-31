<?php

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\ActionGroup;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\UserGroups\CourseAccessResolver;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;

class CoursesRelationManager extends RelationManager
{
    protected static string $relationship = 'courses';

    protected static ?string $title = 'Assigned Courses';

    protected static ?string $modelLabel = 'Course';

    protected static ?string $pluralModelLabel = 'Courses';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->resolveAssignedCoursesQuery())
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
                TextColumn::make('assignment_source')
                    ->label('Assignment')
                    ->badge()
                    ->getStateUsing(fn (Course $record): string => $this->assignmentSourceLabel($record))
                    ->color(fn (string $state): string => match ($state) {
                        'Manual' => 'success',
                        'Group' => 'info',
                        'Manual + Group' => 'warning',
                        default => 'gray',
                    })
                    ->visible(fn (): bool => app(UserGroupCriteriaRegistry::class)->enabled()),
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
                TextColumn::make('assigned_at')
                    ->label('Assigned At')
                    ->getStateUsing(fn (Course $record) => $this->coursePivot($record)?->getAttribute('created_at'))
                    ->dateTime()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query
                            ->leftJoin('lms_course_user as assigned_courses_pivot', function ($join): void {
                                $join->on('assigned_courses_pivot.course_id', '=', 'lms_courses.id')
                                    ->where('assigned_courses_pivot.user_id', $this->getOwnerRecord()->getKey());
                            })
                            ->orderBy('assigned_courses_pivot.created_at', $direction)
                            ->select('lms_courses.*');
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Model $record): bool => $this->hasManualAssignment($record))
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
                        Hidden::make('is_explicitly_assigned')
                            ->default(true),
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    DetachAction::make()
                        ->visible(fn (Model $record): bool => $this->hasManualAssignment($record)),
                ]),
            ], position: RecordActionsPosition::BeforeColumns)
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }

    protected function resolveAssignedCoursesQuery(): Builder
    {
        $user = $this->getOwnerRecord();

        return Course::query()
            ->assignedToUser($user)
            ->with([
                'users' => fn ($query) => $query->whereKey($user->getKey()),
                'userGroups' => fn ($query) => $query
                    ->where('lms_user_groups.is_active', true)
                    ->where('lms_user_groups.published_revision', '>', 0)
                    ->whereHas('memberships', function (Builder $membershipQuery) use ($user): void {
                        $membershipQuery
                            ->where('user_id', $user->getKey())
                            ->whereColumn('revision', 'lms_user_groups.published_revision');
                    }),
            ]);
    }

    protected function coursePivot(Course $record): ?Pivot
    {
        $assignedUser = $record->users->firstWhere('id', $this->getOwnerRecord()->getKey());

        if ($assignedUser === null || ! $assignedUser->relationLoaded('pivot')) {
            return null;
        }

        $pivot = $assignedUser->getRelation('pivot');

        return $pivot instanceof Pivot ? $pivot : null;
    }

    protected function hasManualAssignment(Model $record): bool
    {
        if (! $record instanceof Course) {
            return false;
        }

        return $this->coursePivot($record) !== null;
    }

    protected function assignmentSourceLabel(Course $record): string
    {
        $hasManual = $this->hasManualAssignment($record);
        $hasGroup = $record->relationLoaded('userGroups')
            ? $record->userGroups->isNotEmpty()
            : app(CourseAccessResolver::class)->belongsToAssignedGroup($record, $this->getOwnerRecord());

        return match (true) {
            $hasManual && $hasGroup => 'Manual + Group',
            $hasManual => 'Manual',
            $hasGroup => 'Group',
            default => 'Unknown',
        };
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
