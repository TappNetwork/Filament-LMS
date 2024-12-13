<?php

namespace Tapp\FilamentLms\Resources\LessonResource\Pages;

use Tapp\FilamentLms\Resources\LessonResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateLesson extends CreateRecord
{
    protected static string $resource = LessonResource::class;

    protected function fillForm(): void
{
    parent::fillForm();

    $request = request();

    $this->form->fill($request->query());
}
}
