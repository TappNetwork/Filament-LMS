<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Livewire\Component;

class TestFilamentFormShow extends Component
{
    public function mount(mixed $form = null, bool $blockRedirect = false, bool $allowMultipleSubmissions = false): void
    {
        //
    }

    public function render(): string
    {
        return '<div data-testid="filament-form-show"></div>';
    }
}
