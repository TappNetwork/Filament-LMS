<?php

namespace Tapp\FilamentLms\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
            ->form([
                Select::make('courses')
                    ->label('Courses to Assign')
                    ->multiple()
                    ->options(\Tapp\FilamentLms\Models\Course::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                foreach ($records as $record) {
                    /** @var Model $record */
                    $record->courses()->syncWithoutDetaching($data['courses']);
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
