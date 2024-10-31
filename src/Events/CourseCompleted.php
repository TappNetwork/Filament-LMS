<?php

namespace Tapp\FilamentLms\Events;

use Tapp\FilamentLms\Models\Course;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

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
