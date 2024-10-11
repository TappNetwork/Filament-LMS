<?php

namespace App\Filament\Pages\Training;

use App\Filament\Infolists\Components\GradedKeyValueEntry;
use App\Models\Test;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class ShowGradedTest extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.pages.training.show-graded-test';

    public Test $test;

    public FilamentFormUser $entry;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $this->test = Test::findOrFail(request()->query('test'));
        $this->entry = FilamentFormUser::findOrFail(request()->query('entry'));
    }

    public function gradedTestInfolist(Infolist $infolist): Infolist
    {
        $test = $this->test;

        return $infolist
            ->record($this->entry)
            ->schema([
                TextEntry::make('user.name'),
                TextEntry::make('filamentForm.name')
                    ->label('Form Name'),
                TextEntry::make('created_at')
                    ->label('Form Completed At')
                    ->dateTime(),
                TextEntry::make('percent_correct')
                    ->getStateUsing(function (FilamentFormUser $record) use ($test) {
                        return $test->gradeEntry($record).'%';
                    })
                    ->label('Percent Correct'),
                GradedKeyValueEntry::make('graded_entry')
                    ->label('Graded Entry')
                    ->keyLabel('Question')
                    ->valueLabel('Answer')
                    ->getStateUsing(function (FilamentFormUser $record) use ($test) {
                        return $test->gradedKeyValueEntry($record);
                    }),
            ]);
    }
}
