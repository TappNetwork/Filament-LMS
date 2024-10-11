<?php

namespace App\Filament\App\Pages\Training;

use App\Models\Module;
use App\Models\TrainingDay;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListModules extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.app.pages.training.list-modules';

    public TrainingDay $trainingDay;

    public function getListeners()
    {
        return [
            'refresh' => '$refresh',
        ];
    }

    public function getHeading(): string
    {
        return __($this->trainingDay->training->name.': '.$this->trainingDay->name.' Modules');
    }

    public function mount()
    {
        $this->trainingDay = TrainingDay::findOrFail(request()->query('training_day'))->load('training');
    }

    public function table(Table $table): Table
    {
        $trainingDay = $this->trainingDay;

        return $table
            ->columns([
                TextColumn::make('name'),
                IconColumn::make('completed_at')
                    ->label('Complete?')
                    ->getStateUsing(function (Module $record) use ($trainingDay) {
                        return (bool) $record->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_at;
                    })
                    ->boolean(),
                TextColumn::make('steps_count')
                    ->counts('steps'),
            ])
            ->actions([
                Action::make('mark_complete')
                    ->color('success')
                    ->hidden(function (Module $record) use ($trainingDay) {
                        return $trainingDay->modules->where('id', $record->id)->first()->getRelationValue('pivot')->completed_at;
                    })
                    ->action(function (Module $record, $livewire) use ($trainingDay) {
                        $trainingDay->modules()->updateExistingPivot($record->id, ['completed_at' => now()]);

                        $livewire->dispatch('refresh');
                    }),
                Action::make('mark_incomplete')
                    ->color('danger')
                    ->hidden(function (Module $record) use ($trainingDay) {
                        return ! $trainingDay->modules->where('id', $record->id)->first()->getRelationValue('pivot')->completed_at;
                    })
                    ->action(function (Module $record, $livewire) use ($trainingDay) {
                        $trainingDay->modules()->updateExistingPivot($record->id, ['completed_at' => null]);

                        $livewire->dispatch('refresh');
                    }),
                Action::make('view_steps')
                    ->url(fn (Module $record): string => route('filament.app.pages.list-steps', ['module' => $record->id, 'training_day' => $trainingDay->id])),
            ])
            ->query(Module::whereHas('trainingDays', function (Builder $query) use ($trainingDay) {
                return $query->where('training_days.id', $trainingDay->id)->with('trainingDays');
            }));
    }
}
