<?php

namespace Tapp\FilamentLms\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

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
                    ->options(\Tapp\FilamentLms\Models\Course::all()->pluck('name', 'id'))
                    ->searchable()
                    ->preload()
                    ->required(),
            ])
            ->action(function (Collection $records, array $data) {
                foreach ($records as $record) {
                    /** @var \Illuminate\Database\Eloquent\Model $record */
                    if (method_exists($record, 'courses')) {
                        $record->courses()->syncWithoutDetaching($data['courses']);
                    }
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
