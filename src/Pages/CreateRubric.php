<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Test;

class CreateRubric extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament-lms::filament.pages.create-rubric';

    public $test;

    protected $listeners = ['entrySaved'];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->test = Test::findOrFail(request()->query('test'));
    }

    public function entrySaved(FilamentFormUser $survey)
    {
        $survey->update([
            'user_id' => null,
        ]);

        $this->test->update([
            'filament_form_user_id' => $survey->id,
        ]);

        return redirect(route('filament.admin.pages.view-rubric', ['test' => $this->test->id]));
    }
}
