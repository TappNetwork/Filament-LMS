<?php

namespace App\Filament\App\Widgets\Training;

use App\Models\Training;
use App\Models\TrainingUser;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class UpcomingTrainingDays extends Widget
{
    protected static string $view = 'filament.app.widgets.training.upcoming-training-days';

    public Collection $trainings;

    public Collection $trainingUsers;

    public function mount()
    {
        $this->trainings = Training::whereHas('users', function ($query) {
            return $query->where('users.id', auth()->user()->id);
        })->get();

        $this->trainingUsers = TrainingUser::whereIn('training_id', $this->trainings->pluck('id'))
            ->where('user_id', auth()->user()->id)
            ->with('training')
            ->get();
    }
}
