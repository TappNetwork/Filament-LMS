<?php

namespace App\Filament\App\Pages\Training;

use App\Models\Module;
use App\Models\Step;
use App\Models\TrainingDay;
use Filament\Actions\StaticAction;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class ListSteps extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.app.pages.training.list-steps';

    public Module $module;

    public TrainingDay $trainingDay;

    public function getHeading(): string
    {
        return __($this->module->name.' Steps');
    }

    public function mount()
    {
        $this->module = Module::findOrFail(request()->query('module'))->load('steps');
        $this->trainingDay = TrainingDay::findOrFail(request()->query('training_day'))->load('training');
    }

    public function getListeners()
    {
        return [
            'refresh' => '$refresh',
        ];
    }

    public function table(Table $table): Table
    {
        $module = $this->module;
        $trainingDay = $this->trainingDay;

        return $table
            ->columns([
                TextColumn::make('name'),
                IconColumn::make('has_files')
                    ->boolean()
                    ->getStateUsing(function (Step $record) {
                        return (bool) $record->getMedia('file')->count();
                    }),
                IconColumn::make('is_complete')
                    ->boolean()
                    ->getStateUsing(function (Step $record) use ($module, $trainingDay) {
                        $completedSteps = $module->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_steps;

                        if (! $completedSteps) {
                            return false;
                        }

                        $completedSteps = json_decode($completedSteps);

                        return in_array($record->id, $completedSteps);
                    }),
            ])
            ->actions([
                Action::make('mark_complete')
                    ->hidden(function (Step $record) use ($module, $trainingDay) {
                        $completedSteps = $module->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_steps;

                        if (! $completedSteps) {
                            return false;
                        }

                        $completedSteps = json_decode($completedSteps);

                        return in_array($record->id, $completedSteps);
                    })
                    ->action(function (Step $record, $livewire) use ($trainingDay, $module) {
                        $completedSteps = $module->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_steps;

                        if (! $completedSteps) {
                            $completedSteps = [];
                        } else {
                            $completedSteps = json_decode($completedSteps);
                        }

                        array_push($completedSteps, $record->id);

                        $trainingDay->modules()->updateExistingPivot($module->id, ['completed_steps' => $completedSteps]);

                        $livewire->dispatch('refresh');
                    }),
                Action::make('mark_incomplete')
                    ->hidden(function (Step $record) use ($module, $trainingDay) {
                        $completedSteps = $module->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_steps;

                        if (! $completedSteps) {
                            return false;
                        }

                        $completedSteps = json_decode($completedSteps);

                        return ! in_array($record->id, $completedSteps);
                    })
                    ->color('danger')
                    ->action(function (Step $record, $livewire) use ($trainingDay, $module) {
                        $completedSteps = $module->trainingDays
                            ->where('id', $trainingDay->id)
                            ->first()
                            ->getRelationValue('pivot')
                            ->completed_steps;

                        if (! $completedSteps) {
                            $completedSteps = [];

                            return;
                        } else {
                            $completedSteps = json_decode($completedSteps);
                        }

                        $filteredSteps = [];

                        // Hacky implementation of array_filter because array_filter was returning an associative array which was breaking field
                        for ($i = 0; $i < count($completedSteps); $i++) {
                            if ($completedSteps[$i] != $record->id) {
                                array_push($filteredSteps, $completedSteps[$i]);
                            }
                        }

                        $trainingDay->modules()->updateExistingPivot($module->id, ['completed_steps' => $filteredSteps]);

                        $livewire->dispatch('refresh');
                    }),
                Action::make('view')
                    // @phpstan-ignore-next-line
                    ->modalSubmitAction('')
                    ->modalCancelAction(fn (StaticAction $action) => $action->label('close'))
                    ->infolist([
                        Section::make('Step Information')
                            ->schema([
                                TextEntry::make('name'),
                                TextEntry::make('description'),
                                TextEntry::make('link')
                                    ->html()
                                    ->getStateUsing(function (Step $record) {
                                        return '<a class="hover:underline cursor-pointer text-primary-500" href="'.$record->link.'">'.$record->link.'</a>';
                                    })
                                    ->visible(function (Step $record) {
                                        return $record->link;
                                    }),
                                RepeatableEntry::make('files')
                                    ->getStateUsing(function (Step $record) {
                                        return $record->getMedia('file');
                                    })
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('')
                                            ->html()
                                            ->getStateUsing(function ($record) {
                                                return '<a target="_blank" class="hover:underline cursor-pointer text-primary-500" href="'.$record->getUrl().'">'.$record['name'].'</a>';
                                            }),

                                    ]),
                            ]),
                    ]),

            ])
            ->query(
                Step::where('stepable_id', $module->id)
                    ->where('stepable_type', 'App\Models\Module')
            );
    }
}
