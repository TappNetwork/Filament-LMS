<?php

namespace App\Filament\App\Pages\Training;

use App\Models\Training;
use App\Models\TrainingUser;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;

/**
 * @property ComponentContainer $form
 */
class PhotoVideoConsent extends Page implements HasForms
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.training.photo-video-consent';

    public Training $training;

    public TrainingUser $trainingUser;

    public ?array $data = [];

    public bool $showConfirmation;

    public function mount(): void
    {
        $this->training = Training::findOrFail(request()->query('training'));

        $this->trainingUser = TrainingUser::where('user_id', auth()->user()->id)
            ->where('training_id', $this->training->id)
            ->firstOrFail();

        $this->showConfirmation = (bool) $this->trainingUser->photo_video_consent_at;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Checkbox::make('consent')
                    ->required()
                    ->label('Do you consent to be filmed and/or recorded during the course of this training?'),
            ])
            ->statePath('data');
    }

    public function submit(): void
    {
        $this->form->getState();

        $this->trainingUser->update([
            'photo_video_consent_at' => now(),
        ]);

        $this->showConfirmation = true;
    }
}
