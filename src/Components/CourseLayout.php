<?php

namespace Tapp\FilamentLms\Components;

use Livewire\Component;

class CourseLayout extends Component
{
    public $course;

    public function __construct($course)
    {
        $this->course = $course;
    }

    public function render()
    {
        return view('filament-lms::components.course-layout');
    }
}
