<?php

namespace App\Filament\Pages;

use App\Models\Test;
use Filament\Pages\Page;

class ViewRubric extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.view-rubric';

    public Test $test;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->test = Test::findOrFail(request()->query('test'));
    }
}
