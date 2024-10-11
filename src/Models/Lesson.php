<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_lessons';

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function steps()
    {
        return $this->hasMany(Step::class);
    }
}
