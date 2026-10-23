<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Filament\Support\Icons\Heroicon;
use Tapp\FilamentLms\Forms\Components\MorphToSelectWithCreate;
use Tapp\FilamentLms\Models\Step;

final class MaterialBadge
{
    public static function label(Step $step): string
    {
        if ($step->material === null) {
            return __('filament-lms::course-editor.material.none');
        }

        if ($step->material_type === 'test' && $step->require_perfect_score) {
            return __('filament-lms::course-editor.material.test_perfect');
        }

        return MorphToSelectWithCreate::materialTypeOptions()[$step->material_type] ?? __('filament-lms::course-editor.material.fallback');
    }

    public static function icon(Step $step): Heroicon
    {
        if ($step->material === null) {
            return Heroicon::OutlinedExclamationTriangle;
        }

        return self::iconForType($step->material_type);
    }

    public static function color(Step $step): string
    {
        if ($step->material === null) {
            return 'gray';
        }

        return match ($step->material_type) {
            'video' => 'info',
            'document' => 'primary',
            'link' => 'success',
            'test' => 'warning',
            'image' => 'info',
            default => 'gray',
        };
    }

    public static function iconForType(?string $type): Heroicon
    {
        return match ($type) {
            'video' => Heroicon::OutlinedPlay,
            'document' => Heroicon::OutlinedDocument,
            'link' => Heroicon::OutlinedLink,
            'test' => Heroicon::OutlinedClipboardDocumentCheck,
            'image' => Heroicon::OutlinedPhoto,
            'form' => Heroicon::OutlinedQueueList,
            'library_file', 'library_link' => Heroicon::OutlinedFolder,
            default => Heroicon::OutlinedExclamationTriangle,
        };
    }

    public static function isMissing(Step $step): bool
    {
        return $step->material === null;
    }
}
