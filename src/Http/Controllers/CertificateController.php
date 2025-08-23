<?php

namespace Tapp\FilamentLms\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
// TODO get from config
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tapp\FilamentLms\Models\Course;

class CertificateController extends Controller
{
    public function show($courseId, $userId): View
    {
        $course = Course::findOrFail($courseId);

        $userModel = config('auth.providers.users.model');

        if (! $userModel) {
            throw new \InvalidArgumentException('User model not configured');
        }

        $user = $userModel::findOrFail($userId);

        if (! $user instanceof Authenticatable) {
            throw new \InvalidArgumentException('User model must implement Authenticatable contract');
        }

        if (! request()->hasValidSignature() &&
            ! $course->completedByUserAt($userId) &&
            ! Auth::user()->can('update', $course)) {
            abort(403);
        }

        $view = 'filament-lms::certificates.'.$course->award;

        if (! view()->exists($view)) {
            $view = 'filament-lms::certificates.default';
        }

        $completedAt = $course->completedByUserAt($userId) ?? now();

        return view($view)
            ->with('dateEarned', $completedAt ? Carbon::parse($completedAt)->format(('F j, Y')) : null)
            ->with('user', $user)
            ->with('course', $course);
    }

    public function download(Course $course): StreamedResponse
    {
        if (! $course->completedByUserAt(Auth::id()) && ! Auth::user()->can('update', $course)) {
            abort(403);
        }

        $url = URL::temporarySignedRoute(
            'filament-lms::certificates.show',
            now()->addMinutes(20),
            ['course' => $course, 'user' => Auth::id()]
        );

        $pdf = Browsershot::url($url)
            ->waitUntilNetworkIdle()
            ->showBackground()
            ->landscape()
            ->pdf();

        $filename = Str::slug($course->name).'-'.Str::slug(Auth::user()->name).'-certificate-'.now()->toDateString().'.pdf';

        return response()->stream(function () use ($pdf) {
            echo $pdf;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }
}
