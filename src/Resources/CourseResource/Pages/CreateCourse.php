<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Tapp\FilamentLms\Resources\CourseResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCourse extends CreateRecord
{
    protected static string $resource = CourseResource::class;
}
