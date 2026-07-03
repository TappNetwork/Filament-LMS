<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Livewire\Component;

class TestFilamentFormShow extends Component
{
    public static ?bool $lastAllowMultipleSubmissions = null;

    public static function resetLastAllowMultipleSubmissions(): void
    {
        self::$lastAllowMultipleSubmissions = null;
    }

    public function mount(mixed $form = null, bool $blockRedirect = false, bool $allowMultipleSubmissions = false): void
    {
        self::$lastAllowMultipleSubmissions = $allowMultipleSubmissions;
    }

    public function render(): string
    {
        return '<div data-testid="filament-form-show"></div>';
    }
}
