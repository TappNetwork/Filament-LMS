<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Models\UserType;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property ComponentContainer $form
 */
class Register extends BaseRegister
{
    public function form(Form $form): Form
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
                    ->unique()
                    ->required(),
                TextInput::make('password')
                    ->label(__('filament-panels::pages/auth/register.form.password.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->rule(Password::default())
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->same('passwordConfirmation')
                    ->validationAttribute(__('filament-panels::pages/auth/register.form.password.validation_attribute')),
                TextInput::make('passwordConfirmation')
                    ->label(__('filament-panels::pages/auth/register.form.password_confirmation.label'))
                    ->password()
                    ->revealable(filament()->arePasswordsRevealable())
                    ->required()
                    ->dehydrated(false),
                TextInput::make('secondary_email')
                    ->label('Secondary Email')
                    ->helperText('Your professional, work, or secondary email address.')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->required(),
                Select::make('organization_id')
                    ->live()
                    ->preload()
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
                Radio::make('core_competency_training_status')
                    ->label('Have you completed Core Competency Training')
                    ->options([
                        'yes' => 'Yes',
                        'in_progress' => 'In Progress',
                        'no' => 'No',
                    ])
                    ->live()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('user_type_id') == $chwTypeId),
                Select::make('program_id')
                    ->relationship(name: 'program', titleAttribute: 'name')
                    ->preload()
                    ->label('Which training program did you complete?')
                    ->live()
                    ->required()
                    ->visible(fn (Get $get): bool => (bool) ($get('core_competency_training_status') == 'yes')),
                TextInput::make('program_name')
                    ->visible(fn (Get $get): bool => $get('program_id') == $otherProgramId)
                    ->label('Other Program Name')
                    ->required(),
                Radio::make('chw_status')
                    ->label('Are you a certified Community Health Worker?')
                    ->options([
                        'not_certified' => 'Not Certified',
                        'in_progress' => 'In Progress',
                        'certified' => 'Certified',
                    ])
                    ->live()
                    ->required()
                    ->visible(fn (Get $get): bool => $get('user_type_id') == $chwTypeId),
            ])
            ->statePath('data')
            ->model(User::class);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! isset($data['chw_status'])) {
            $data['chw_status'] = 'not_certified';
        }

        return $data;
    }
}
