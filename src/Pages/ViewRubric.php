<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Pages\Page;
use Tapp\FilamentLms\Models\Test;

class ViewRubric extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament-lms::filament.pages.view-rubric';

    public $test;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->test = Test::findOrFail(request()->query('test'));
    }
}
