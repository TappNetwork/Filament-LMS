<?php

namespace Tapp\FilamentLms\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tapp\FilamentLms\Models\Course;

class CourseCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public Course $course;

    /**
     * Create a new event instance.
     */
    public function __construct($user, Course $course)
    {
        $this->user = $user;
        $this->course = $course;
    }
}
