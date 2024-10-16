<?php

namespace App\Filament\Pages;

use App\Models\Test;
use Filament\Pages\Page;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class CreateRubric extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.create-rubric';

    public Test $test;

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
        $this->test->update([
            'filament_form_user_id' => $survey->id,
        ]);

        return redirect(route('filament.admin.pages.view-rubric', ['test' => $this->test->id]));
    }
}