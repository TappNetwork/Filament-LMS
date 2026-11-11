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
                $courses = Course::query()
                    ->whereIn('id', $data['courses'])
                    ->get();

                foreach ($records as $record) {
                    if (! method_exists($record, 'courses')) {
                        continue;
                    }

                    foreach ($courses as $course) {
                        $course->assignUsers([$record->getKey()]);
                    }
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
