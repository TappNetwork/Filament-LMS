<?php

namespace Tapp\FilamentLms\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Tapp\FilamentLms\Models\Step;

class StepCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user;

    public Step $step;

    /**
     * Create a new event instance.
     */
    public function __construct($user, Step $step)
    {
        $this->user = $user;
        $this->step = $step;
    }
}
