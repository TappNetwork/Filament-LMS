<?php

namespace Tapp\FilamentLms\Livewire;

use Exception;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
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

    public function gradedTestInfolist(Schema $schema): Schema
    {
        $test = $this->test;
        $grade = null;

        try {
            $gradeResult = $test->gradeEntry($this->entry);
            if ($gradeResult instanceof Exception) {
                $grade = null;
            } else {
                $grade = $gradeResult;
            }
        } catch (Exception $e) {
            // Grade calculation failed, but we'll still show the form
        }

        return $schema
            ->record($this->entry)
            ->components([
                Section::make('Test Results')
                    ->schema([
                        TextEntry::make('test_name')
                            ->label('Test')
                            ->state($test->name),
                        TextEntry::make('grade')
                            ->label('Grade')
                            ->state($grade !== null ? $grade.'%' : 'N/A')
                            ->color(fn ($state) => $grade !== null ? ($grade >= 70 ? 'success' : ($grade >= 50 ? 'warning' : 'danger')) : 'gray'),
                        TextEntry::make('submission_status')
                            ->label('Status')
                            ->state('Completed')
                            ->color('success'),
                        TextEntry::make('submitted_at')
                            ->label('Submitted')
                            ->state($this->entry->created_at->format('M j, Y g:i A')),
                    ])
                    ->columns(2),

                Section::make('Detailed Results')
                    ->schema(function () use ($test) {
                        try {
                            $gradedResults = $test->gradedKeyValueEntry($this->entry);
                            if ($gradedResults instanceof Exception) {
                                return [];
                            }

                            $components = [];
                            foreach ($gradedResults as $question => $data) {
                                $isCorrect = $data['correct'] ?? false;
                                $answer = $data['answer'] ?? '';

                                $components[] = TextEntry::make("question_{$question}")
                                    ->label($question)
                                    ->state("Your answer: {$answer}")
                                    ->icon($isCorrect ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle')
                                    ->iconColor($isCorrect ? 'success' : 'danger')
                                    ->columnSpanFull();
                            }

                            return $components;
                        } catch (Exception $e) {
                            return [];
                        }
                    })
                    ->collapsible()
                    ->collapsed()
                    ->description('Click to view detailed question-by-question results')
                    ->extraAttributes(['class' => 'mb-8']),
            ]);
    }
}
