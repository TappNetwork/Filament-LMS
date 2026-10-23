<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tapp\FilamentLms\Concerns\RemembersErrorBag;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\CourseResource\Pages\Concerns\HasCourseEditorTabs;

class CreateCourse extends CreateRecord
{
    use HasCourseEditorTabs;
    use RemembersErrorBag;

    protected static string $resource = CourseResource::class;
}
