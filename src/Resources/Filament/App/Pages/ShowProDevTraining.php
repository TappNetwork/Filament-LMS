<?php

namespace App\Filament\App\Pages;

use App\Models\ProDevTraining;
use App\Notifications\ProDevTraining\RegistrationConfirmation;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\Actions;
use Filament\Infolists\Components\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Concerns\InteractsWithInfolists;
use Filament\Infolists\Contracts\HasInfolists;
use Filament\Infolists\Infolist;
use Filament\Pages\Page;

class ShowProDevTraining extends Page implements HasForms, HasInfolists
{
    use InteractsWithForms;
    use InteractsWithInfolists;

    public ?ProDevTraining $training;

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.show-pro-dev-training';

    protected static ?string $slug = 'professional-development-info';

    public function getHeading(): string
    {
        return __($this->training->name.' Information');
    }

    public function mount()
    {
        $this->training = ProDevTraining::find(request()->query('training'));

        if (! $this->training) {
            return redirect(ProDevTrainingCalendar::getUrl());
        }
    }

    public function proDevTrainingInfoList(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->training)
            ->schema([
                TextEntry::make('name'),
                TextEntry::make('starts_at')
                    ->dateTime('F j, Y g:i A'),
                TextEntry::make('speaker_name'),
                TextEntry::make('speaker_org'),
                TextEntry::make('description'),
                Actions::make([
                    Action::make('register')
                        ->color('success')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                ! $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->action(function (ProDevTraining $record) {
                            $record
                                ->registeredUsers()
                                ->syncWithoutDetaching(auth()->user());

                            auth()->user()->notify(new RegistrationConfirmation($record));
                        }),
                    Action::make('unregister')
                        ->label('Cancel Registration')
                        ->color('danger')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->action(function (ProDevTraining $record) {
                            $record
                                ->registeredUsers()
                                ->detach(auth()->user());
                        }),
                ]),
                Actions::make([
                    Action::make('zoom_link')
                        ->label('Meeting Link')
                        ->color('primary')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->url(function (ProDevTraining $record) {
                            return url($record->zoom_link);
                        }),
                    Action::make('ical_link_apple')
                        ->label('')
                        ->icon('icon-apple-logo')
                        ->color('primary')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->url(function (ProDevTraining $record) {
                            return $record->ics_calendar_link;
                        }),
                    Action::make('ical_link_outlook')
                        ->label('')
                        ->icon('icon-outlook-logo')
                        ->color('primary')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->url(function (ProDevTraining $record) {
                            return $record->ics_calendar_link;
                        }),
                    Action::make('google_calendar_link')
                        ->label('')
                        ->icon('icon-google-logo')
                        ->color('primary')
                        ->visible(function (ProDevTraining $record) {
                            return
                                auth()->check() &&
                                $record->starts_at > now() &&
                                $record
                                    ->registeredUsers()
                                    ->where('user_id', auth()->user()->id)
                                    ->exists();
                        })
                        ->url(function (ProDevTraining $record) {
                            return $record->google_calendar_link;
                        }),
                ]),
            ]);
    }
}
