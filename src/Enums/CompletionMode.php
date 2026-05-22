<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Enums;

enum CompletionMode: string
{
    case Native = 'native';
    case Scorm12 = 'scorm12';
    case Html5 = 'html5';
}
