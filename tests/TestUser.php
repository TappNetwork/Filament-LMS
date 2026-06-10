<?php

namespace Tapp\FilamentLms\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Notifications\Notifiable;
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class TestUser extends User
{
    use FilamentLmsUser;
    use Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $table = 'users';
}
