<?php

namespace App\Filament\App\Pages;

use App\Models\Test;
use App\Models\Training;
use App\Models\TrainingUser;
use Filament\Pages\Page;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class CreateTestEntry extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.create-test-entry';

    public Test $test;

    public Training $training;

    public TrainingUser $trainingUser;

    public string $testType;

    public bool $showResults = false;

    public float $percentageCorrect;

    public int $questionsCorrect;

    public ?FilamentFormUser $entry = null;

    protected $listeners = ['entrySaved'];

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public function mount()
    {
        $this->training = Training::findOrFail(request()->query('training'));
        $this->test = Test::findOrFail(request()->query('test'))->load('form');
        $this->testType = request()->query('test_type');

        $testTypeIdColumn = $this->testType.'_id';

        if ($this->test->id != $this->training->$testTypeIdColumn) {
            return redirect('/dashboard');
        }

        $this->trainingUser = TrainingUser::where('user_id', auth()->user()->id)
            ->where('training_id', $this->training->id)
            ->firstOrFail();

        $testEntryIdColumn = $this->testType.'_entry_id';

        if ($this->trainingUser->$testEntryIdColumn) {
            $this->populateCompletedTestData();

            $this->entry = FilamentFormUser::findOrFail($this->trainingUser->$testEntryIdColumn);
        }
    }

    public function entrySaved(FilamentFormUser $survey)
    {
        $this->trainingUser->update([
            $this->testType.'_entry_id' => $survey->id,
            $this->testType.'_grade' => $this->test->gradeEntry($survey),
        ]);

        $this->populateCompletedTestData();

        $this->entry = $survey;
    }

    private function populateCompletedTestData()
    {
        $this->showResults = true;

        $this->percentageCorrect = $this->trainingUser->toArray()[$this->testType.'_grade'];

        $this->questionsCorrect = $this->test->form->filamentFormFields->count() * ($this->percentageCorrect / 100);
    }
}
