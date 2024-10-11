<?php

namespace App\Filament\App\Widgets;

use App\Models\TrainingUser;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class TrainingProgress extends Widget
{
    protected static string $view = 'filament.app.widgets.training-progress';

    public Collection $trainingUsers;

    public function mount()
    {
        $this->trainingUsers = TrainingUser::where('user_id', auth()->user()->id)
            ->with([
                'training',
                'training.trainingDays',
                'training.trainingDays.modules',
                'training.certification.modules',
            ])->get();
    }

    public function getModuleClasses($trainingUser, $trainingDay, $module)
    {
        $isCompleted = in_array($trainingDay->id, $trainingUser->trainingDays->pluck('id')->toArray())
            && in_array($module->id, $trainingUser->modules->pluck('id')->toArray());

        if (! $isCompleted) {
            return 'from-blue-200 to-purple-300 hover:from-blue-300 hover:to-purple-300';
        }

        return 'from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700';
    }
}
