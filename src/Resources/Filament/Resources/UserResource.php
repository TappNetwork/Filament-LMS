<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Tables;
use App\Models\Program;
use Filament\Forms\Get;
use App\Models\UserType;
use Filament\Forms\Form;
use Filament\Tables\Table;
use App\Exports\UsersExport;
use App\Models\Organization;
use Filament\Resources\Resource;
use Illuminate\Validation\Rules;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Tapp\FilamentInvite\Tables\InviteAction;
use App\Filament\Resources\UserResource\Pages;
use STS\FilamentImpersonate\Tables\Actions\Impersonate;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        $otherOrganizationId = Organization::where('name', 'Other')->first()->id;
        $otherProgramId = Program::where('name', 'Other')->first()->id;
        $chwTypeId = UserType::where('name', 'Community Health Worker')->first()->id;

        return $form
            ->schema([
                TextInput::make('first_name')
                    ->required(),
                TextInput::make('last_name')
                    ->required(),
                TextInput::make('email')
                    ->label('Primary Email')
                    ->helperText('Using your personal email address is recommended as this account is associated with you as an individuals rather than your employer.')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('secondary_email')
                    ->label('Secondary Email')
                    ->helperText('Your professional, work, or secondary email address.')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->hidden(fn (string $context): bool => $context === 'create')
                    ->hint('Leave blank to keep the same password')
                    ->rules([Rules\Password::defaults()]),
                SpatieMediaLibraryFileUpload::make('profile_photo'),
                Select::make('organization_id')
                    ->live()
                    ->relationship(name: 'organization', titleAttribute: 'name')
                    ->required(),
                TextInput::make('organization_name')
                    ->visible(fn (Get $get): bool => $get('organization_id') == $otherOrganizationId)
                    ->label('Other Organization Name')
                    ->required(),
                Select::make('user_type_id')
                    ->relationship(name: 'type', titleAttribute: 'name')
                    ->live()
                    ->label('Type of User')
                    ->required(),
                Radio::make('chw_status')
                    ->label('Are you a certified Community Health Worker?')
                    ->options([
                        'not_certified' => 'Not Certified',
                        'in_progress' => 'In Progress',
                        'certified' => 'Certified',
                    ])
                    ->live()
                    ->visible(fn (Get $get): bool => $get('user_type_id') == $chwTypeId),
                Toggle::make('contact_method_email')
                    ->label('Email notifications'),
                Select::make('program_id')
                    ->relationship(name: 'program', titleAttribute: 'name')
                    ->preload()
                    ->visible(fn (Get $get): bool => (bool) ($get('chw_status') == 'certified'))
                    ->label('Which training program did you complete?')
                    ->live()
                    ->required(),
                TextInput::make('program_name')
                    ->visible(fn (Get $get): bool => $get('program_id') == $otherProgramId)
                    ->label('Other Program Name')
                    ->required(),
                Select::make('roles')
                    ->multiple()
                    ->relationship('roles', 'name')
                    ->preload()
                    ->visible(fn (): bool => auth()->user()->can('edit user roles')),
                Select::make('counties')
                    ->multiple()
                    ->relationship('counties', 'name')
                    ->preload(),
                TagsInput::make('credentials'),
                TagsInput::make('education'),
                TagsInput::make('specializations'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('first_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('last_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Personal Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('work_email')
                    ->label('Work Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('organization.name')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('counties')
                    ->relationship('counties', 'name')
                    ->multiple()
                    ->preload()
                    ->label('County')
            ])
            ->actions([
                Impersonate::make(),
                InviteAction::make()
                    ->hidden(fn (User $user): bool => ! auth()->user()->can('update', $user) || $user->hasVerifiedEmail()),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
                BulkAction::make('export')
                    ->label('Export Users')
                    ->action(fn (Collection $records) => Excel::download(new UsersExport($records), 'users_'.now()->format('Y-m-d').'.xlsx'))
                    ->icon('heroicon-o-document-chart-bar')
                    ->deselectRecordsAfterCompletion(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
