<?php

namespace App\Filament\Resources\TrainingResource\RelationManagers;

use App\Models\Training;
use App\Models\TrainingUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Table;

/**
 * @method Training getOwnerRecord()
 */
class WaitListedUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'waitListedUsers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
            ])
            ->actions([
                Action::make('move_off_wait_list')
                    ->label('Remove user from wait list')
                    ->color('primary')
                    ->action(function (User $record) {
                        TrainingUser::where('training_id', $this->getOwnerRecord()->id)
                            ->where('user_id', $record->id)
                            ->firstOrFail()
                            ->removeFromWaitList();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
