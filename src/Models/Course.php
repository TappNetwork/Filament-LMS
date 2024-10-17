<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_courses';

    protected $casts = [
        'award_content' => 'array',
    ];

    public function lessons()
    {
        return $this->hasMany(Lesson::class);
    }
}
