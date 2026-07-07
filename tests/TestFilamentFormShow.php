<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests;

use Illuminate\Contracts\Support\MessageBag as MessageBagContract;
use Illuminate\Support\MessageBag;
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

    public function getErrorBag(): MessageBagContract
    {
        $errorBag = parent::getErrorBag();

        return $errorBag instanceof MessageBagContract ? $errorBag : new MessageBag;
    }

    public function render(): string
    {
        return '<div data-testid="filament-form-show"></div>';
    }
}
