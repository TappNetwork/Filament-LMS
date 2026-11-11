<?php

namespace Tapp\FilamentLms\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Tapp\FilamentLms\Models\Course;

class AssignCoursesBulkAction extends BulkAction
{
    public static function getDefaultName(): string
    {
        return 'assign_courses';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Assign Courses')
            ->icon('heroicon-o-academic-cap')
            ->schema([
                Select::make('courses')
                    ->label('Courses to Assign')
                    ->multiple()
                    ->options(Course::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $this->assignCourses($records, $data['courses']);
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * One assignUsers() call per course. That helper already chunks lookup and
     * CourseAssigned dispatch, so the selected ids must be passed together.
     *
     * @param  array<int, int|string>  $courseIds
     */
    protected function assignCourses(Collection $records, array $courseIds): void
    {
        $userIds = [];

        foreach ($records as $record) {
            if (! method_exists($record, 'courses')) {
                continue;
            }

            $userIds[] = $record->getKey();
        }

        if ($userIds === []) {
            return;
        }

        Course::query()
            ->whereIn('id', $courseIds)
            ->get()
            ->each(fn (Course $course) => $course->assignUsers($userIds));
    }
}
