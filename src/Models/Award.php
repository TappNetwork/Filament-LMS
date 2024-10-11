<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_awards';

    public function course()
    {
        return $this->hasOne(Course::class);
    }
}
