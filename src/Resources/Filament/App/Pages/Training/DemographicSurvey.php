<?php

namespace App\Filament\App\Pages\Training;

use App\Models\Training;
use App\Models\TrainingUser;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * @property ComponentContainer $form
 */
class DemographicSurvey extends Page implements HasForms
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.training.demographic-survey';

    public Training $training;

    public TrainingUser $trainingUser;

    public ?array $data = [];

    public bool $showConfirmation;

    protected static bool $shouldRegisterNavigation = false;

    public function mount(): void
    {
        $this->training = Training::findOrFail(request()->query('training'));

        $this->trainingUser = TrainingUser::where('user_id', auth()->user()->id)
            ->where('training_id', $this->training->id)
            ->firstOrFail();

        $this->showConfirmation = (bool) $this->trainingUser->demographic_survey;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('how did you find out about CHECK?')
                    ->label('How did you find out about CHECK?')
                    ->required(),
                TextInput::make('how long have you been considering taking a training through CHECK?')
                    ->label('How long have you been considering taking a training through CHECK?')
                    ->required(),
                TextInput::make('What is your age?')
                    ->label('What is your age?')
                    ->numeric()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $formData = $this->form->getState();

        $this->trainingUser->update([
            'demographic_survey' => $formData,
        ]);

        $this->showConfirmation = true;
    }
}
