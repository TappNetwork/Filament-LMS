<?php

namespace Tapp\FilamentLms\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class TestUser extends User
{
    use FilamentLmsUser;
    use HasApiTokens;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $table = 'users';
}
