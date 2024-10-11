<?php

namespace App\Filament\App\Pages;

use App\Models\ProDevTraining;
use App\Notifications\ProDevTraining\RegistrationConfirmation;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProDevTrainingCalendar extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $view = 'filament.app.pages.pro-dev-training-calendar';

    protected static ?string $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $slug = 'professional-development';

    protected ?string $heading = 'Professional Development Calendar';

    public static function getNavigationLabel(): string
    {
        return 'Professional Development';
    }

    public function getHeaderActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getQuery())
            ->columns($this->getColumns())
            ->defaultSort('starts_at', 'asc')
            ->filters($this->getFilters())
            ->actions($this->tableActions())
            ->recordUrl(fn (ProDevTraining $record) => ShowProDevTraining::getUrl(['training' => $record->id]))
            ->paginated([12, 24, 48, 96])
            ->contentGrid([
                'xl' => 2,
                '2xl' => 3,
            ]);
    }

    public function getQuery(): Builder
    {
        return ProDevTraining::query();
    }

    public function getColumns(): array
    {
        return [
            Stack::make([
                \App\Tables\Columns\TitleColumn::make('name')
                    ->searchable(),
                Stack::make([
                    TextColumn::make('starts_at')
                        ->dateTime('F j, Y g:i A')
                        ->sortable(),
                    TextColumn::make('speaker_name')
                        ->searchable(),
                    TextColumn::make('speaker_org')
                        ->searchable(),
                ]),
                \App\Tables\Columns\DescriptionColumn::make('description')
                    ->searchable(),
            ])->space(2),
        ];
    }

    public function tableActions(): array
    {
        return [
            TableAction::make('register')
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
            TableAction::make('unregister')
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
            TableAction::make('details')
                ->url(function (ProDevTraining $record) {
                    // return route('pro-dev-training.show', $record);
                    return ShowProDevTraining::getUrl(['training' => $record->id]);
                }),
        ];
    }

    public function getFilters(): array
    {
        return [
            Filter::make('future_trainings')
                ->query(fn (Builder $query): Builder => $query->whereDate('starts_at', '>=', now()))
                ->default(),
        ];
    }
}
