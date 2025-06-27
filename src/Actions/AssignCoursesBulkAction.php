<?php

namespace Tapp\FilamentLms\Actions;

use Filament\Forms;
use Filament\Tables\Actions\BulkAction;

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
                Forms\Components\Select::make('courses')
                    ->preload()
                    ->multiple()
                    // TODO: relationship is not working
                    // ->relationship('courses', 'name')
                    ->options(\Tapp\FilamentLms\Models\Course::pluck('name', 'id')->toArray())
                    ->required(),
            ]);
    }
}
