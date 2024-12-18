<?php

namespace Tapp\FilamentLms\Resources\LessonResource\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tapp\FilamentLms\Resources\LessonResource;

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
