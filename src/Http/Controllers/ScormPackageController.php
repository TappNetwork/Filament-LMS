<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Step;

final class ScormPackageController extends Controller
{
    public function show(Request $request, Document $document): BinaryFileResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless($document->hasScormPackage(), 404);

        $course = $this->resolveCourseForDocument($document);
        abort_unless($course !== null, 404);

        $user = Auth::user();
        abort_unless($user !== null && Course::accessibleTo($user)->whereKey($course->id)->exists(), 403);

        $disk = $document->package_disk ?: (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
        $packageRoot = Storage::disk($disk)->path($document->package_path);
        $relativePath = (string) $request->query('entry', $document->getScormLaunchPath());
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        abort_if(str_contains($relativePath, '..'), 404);

        $fullPath = $packageRoot.'/'.$relativePath;
        $realPackageRoot = realpath($packageRoot);
        $realFile = realpath($fullPath);
        abort_if($realPackageRoot === false || $realFile === false, 404);
        abort_unless(str_starts_with($realFile, $realPackageRoot), 404);
        abort_unless(is_file($realFile), 404);

        return response()->file($realFile, [
            'Content-Type' => $this->mimeType($realFile),
        ]);
    }

    private function resolveCourseForDocument(Document $document): ?Course
    {
        $step = Step::query()
            ->where('material_type', 'document')
            ->where('material_id', $document->id)
            ->with('lesson.course')
            ->first();

        return $step?->lesson?->course;
    }

    private function mimeType(string $path): string
    {
        $extension = mb_strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'html', 'htm' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            default => 'application/octet-stream',
        };
    }
}
