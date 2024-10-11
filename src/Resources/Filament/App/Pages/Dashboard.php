<?php

namespace App\Filament\App\Pages;

use App\Filament\App\Widgets\Training\UpcomingTrainingDays;
use App\Filament\App\Widgets\TrainingProgress;
use App\Filament\App\Widgets\UpcomingTrainings;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.training.training-dashboard';

    protected static string $routePath = '/dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            TrainingProgress::class,
            UpcomingTrainingDays::class,
            UpcomingTrainings::class,
        ];
    }
}
