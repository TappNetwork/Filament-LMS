<?php

namespace App\Filament\App\Pages;

use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Models\UserType;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Profile extends BaseEditProfile
{
    public User $user;

    public function __construct()
    {
        $this->user = auth()->user();
    }

    public function form(Form $form): Form
    {
        $otherOrganizationId = Organization::where('name', 'Other')->first()->id;
        $otherProgramId = Program::where('name', 'Other')->first()->id;
        $chwTypeId = UserType::where('name', 'Community Health Worker')->first()->id;

        return $form
            ->schema([
                Section::make('User Information')
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
                        Toggle::make('contact_method_email')
                            ->label('Email notifications'),
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
                            ->default('not_certified')
                            ->live()
                            ->visible(fn (Get $get): bool => $get('user_type_id') == $chwTypeId),
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
                    ]),
                Section::make('Change Password')
                    ->description('Leave these fields blank if you do not want to change your password.')
                    ->schema([
                        TextInput::make('password')
                            ->label(__('filament-panels::pages/auth/edit-profile.form.password.label'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->rule(Password::default())
                            ->autocomplete('new-password')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->dehydrateStateUsing(fn ($state): string => Hash::make($state))
                            ->live(debounce: 500)
                            ->same('passwordConfirmation'),
                        TextInput::make('passwordConfirmation')
                            ->label(__('filament-panels::pages/auth/edit-profile.form.password_confirmation.label'))
                            ->password()
                            ->revealable(filament()->arePasswordsRevealable())
                            ->required()
                            ->visible(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data')
            ->model($this->user);
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
