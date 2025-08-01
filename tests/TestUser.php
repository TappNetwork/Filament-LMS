<?php

namespace Tapp\FilamentLms\Tests;

use Illuminate\Foundation\Auth\User;
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class TestUser extends User
{
    use FilamentLmsUser;

    protected $fillable = ['name', 'email', 'password'];

    protected $table = 'users';
}
