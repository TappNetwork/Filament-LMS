<?php

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Step extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_steps';

    public function lesson()
    {
        return $this->belongsTo(Lesson::class);
    }

    public function material()
    {
        return $this->morphTo();
    }
}
