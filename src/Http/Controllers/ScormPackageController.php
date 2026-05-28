<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Step;

final class ScormPackageController extends Controller
{
    public function show(Request $request, Document $document, ?string $entry = null): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless($document->hasScormPackage(), 404);

        $course = $this->resolveCourseForDocument($document);
        abort_unless($course !== null, 404);

        $user = Auth::user();
        abort_unless($user !== null && Course::accessibleTo($user)->whereKey($course->id)->exists(), 403);

        $packageRoot = $this->resolvePackageRoot($document);
        abort_if($packageRoot === null, 404);

        // Path-based URLs (e.g. /lms/scorm-package/1/index.html) let relative asset paths resolve correctly.
        $relativePath = $entry ?? (string) $request->query('entry', $document->getScormLaunchPath());
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        abort_if(str_contains($relativePath, '..'), 404);

        $fullPath = $packageRoot.'/'.$relativePath;
        $realPackageRoot = realpath($packageRoot);
        $realFile = realpath($fullPath);
        abort_if($realPackageRoot === false || $realFile === false, 404);
        abort_unless(str_starts_with($realFile, $realPackageRoot), 404);
        abort_unless(is_file($realFile), 404);

        $mimeType = $this->mimeType($realFile);
        $headers = $this->headersForMimeType($mimeType);

        if ($course->isEmbeddedPlayer()
            && $course->completionMode() === CompletionMode::Html5
            && in_array($mimeType, ['text/html'], true)) {
            $content = file_get_contents($realFile);
            if (is_string($content)) {
                $content = $this->injectHtml5BridgeIntoHtml($content);

                return response($content, 200, $headers);
            }
        }

        return response()->file($realFile, $headers);
    }

    private function injectHtml5BridgeIntoHtml(string $content): string
    {
        $script = view('filament-lms::components.html5-package-bridge-script')->render();

        if (str_contains($content, '</body>')) {
            return str_replace('</body>', $script.'</body>', $content);
        }

        return $content.$script;
    }

    private function resolvePackageRoot(Document $document): ?string
    {
        if ($document->package_path === null || $document->package_path === '') {
            return null;
        }

        $disk = $document->package_disk ?: (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
        $configuredRoot = Storage::disk($disk)->path($document->package_path);

        if (is_dir($configuredRoot)) {
            return $configuredRoot;
        }

        // Imports before Laravel 12 disk layout stored packages under storage/app/ directly.
        $legacyRoot = storage_path('app/'.$document->package_path);

        return is_dir($legacyRoot) ? $legacyRoot : null;
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

    /**
     * @return array<string, string>
     */
    private function headersForMimeType(string $mimeType): array
    {
        $headers = [
            'Content-Type' => $mimeType,
        ];

        if ($mimeType === 'image/svg+xml') {
            $headers['Content-Security-Policy'] = "script-src 'none'; sandbox";
            $headers['X-Content-Type-Options'] = 'nosniff';
        }

        return $headers;
    }
}
