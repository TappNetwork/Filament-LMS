<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Support\MessageBag;
use Livewire\Component;

class TestFilamentFormUserShow extends Component
{
    public function mount(mixed $entry = null): void
    {
        //
    }

    public function getErrorBag(): MessageBagContract
    {
        $errorBag = parent::getErrorBag();

        return $errorBag instanceof MessageBagContract ? $errorBag : new MessageBag;
    }

    public function render(): string
    {
        return '<div data-testid="filament-form-user-show"></div>';
    }
}
