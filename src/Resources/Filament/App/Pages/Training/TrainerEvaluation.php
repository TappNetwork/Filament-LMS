<?php

namespace App\Filament\App\Pages\Training;

use App\Models\Training;
use App\Models\TrainingUser;
use Filament\Pages\Page;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class TrainerEvaluation extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.training.trainer-evaluation';

    protected static ?string $slug = 'trainer-evaluation';

    public function getHeading(): string
    {
        return __('Trainer Evaluation for '.$this->training->trainer->name);
    }

    protected static bool $shouldRegisterNavigation = false;

    public FilamentForm $form;

    public Training $training;

    public ?TrainingUser $trainingUser;

    protected $listeners = ['entrySaved'];

    public function mount(FilamentForm $form, Training $training)
    {
        $this->training = Training::find(request()->query('training'));

        $this->trainingUser = TrainingUser::where('user_id', auth()->user()->id)
            ->where('training_id', $this->training->id)
            ->first();

        if (
            ! $this->training ||
            ! $this->trainingUser
        ) {
            redirect(route('filament.app.pages.dashboard'));
        }

        $this->form = $this->training->trainerEvaluation;
    }

    public function entrySaved(FilamentFormUser $entry)
    {
        $this->trainingUser->update([
            'trainer_eval_id' => $entry->id,
        ]);
    }
}
