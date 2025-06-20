<?php

namespace Tapp\FilamentLms\Livewire;

use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Livewire\Component;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Test;

class ViewGradedEntry extends Component implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public Test $test;

    public FilamentFormUser $entry;

    public function mount(Test $test, FilamentFormUser $entry)
    {
        $this->test = $test;
        $this->entry = $entry;
    }

    public function render()
    {
        return view('filament-lms::livewire.view-graded-entry');
    }

    public function gradedTestInfolist(Infolist $infolist): Infolist
    {
        $test = $this->test;

        return $infolist
            ->record($this->entry)
            ->schema([
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
