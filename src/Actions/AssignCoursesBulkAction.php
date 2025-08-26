<?php

namespace Tapp\FilamentLms\Actions;

use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Tapp\FilamentLms\Models\Course;

class AssignCoursesBulkAction
{
    public static function make(string $name = 'assign_courses'): BulkAction
    {
        return BulkAction::make($name)
            ->icon('heroicon-o-academic-cap')
            ->action(function ($records, $data) {
                $courseIds = $data['courses'] ?? [];
                foreach ($records as $user) {
                    $user->courses()->syncWithoutDetaching($courseIds);
                }
            })
            ->form([
                Select::make('courses')
                    ->preload()
                    ->multiple()
                    // TODO: relationship is not working
                    // ->relationship('courses', 'name')
                    ->options(Course::pluck('name', 'id')->toArray())
                    ->required(),
            ]);
    }
}
