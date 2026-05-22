<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\ScormProgressService;

final class ScormCommitController extends Controller
{
    public function store(Request $request, Course $course): JsonResponse
    {
        abort_unless(Auth::check(), 403);
        abort_unless($course->isEmbeddedPlayer(), 404);

        $user = Auth::user();
        abort_unless($user !== null && Course::accessibleTo($user)->whereKey($course->id)->exists(), 403);

        $validated = $request->validate([
            'lesson_status' => ['nullable', 'string', 'max:64'],
            'lesson_location' => ['nullable', 'string', 'max:255'],
            'suspend_data' => ['nullable', 'string'],
            'score' => ['nullable', 'string', 'max:64'],
            'html5_complete' => ['nullable', 'boolean'],
            'html5_progress' => ['nullable', 'boolean'],
            'initialized' => ['nullable', 'boolean'],
            'finished' => ['nullable', 'boolean'],
        ]);

        $service = app(ScormProgressService::class);

        if (! empty($validated['html5_complete'])) {
            $result = $service->attemptManualCourseCompletion($course, $user);

            return response()->json($result, $result['ok'] ? 200 : 422);
        }

        $service->processCommit($course, $user, [
            'lesson_status' => $validated['lesson_status'] ?? null,
            'lesson_location' => $validated['lesson_location'] ?? null,
            'suspend_data' => $validated['suspend_data'] ?? null,
            'score' => $validated['score'] ?? null,
            'html5_progress' => $validated['html5_progress'] ?? false,
        ]);

        return response()->json(['ok' => true]);
    }
}
