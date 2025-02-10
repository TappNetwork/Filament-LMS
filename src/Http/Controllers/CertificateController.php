<?php

namespace Tapp\FilamentLms\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
// TODO get from config
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tapp\FilamentLms\Models\Course;

class CertificateController extends Controller
{
    // TODO does this need authentication to prevent direct visits?
    public function show($courseId, $userId): View
    {
        if (! request()->hasValidSignature() && ! (auth()->user() && auth()->user()->hasRole('Admin'))) {
            abort(403);
        }

        $course = Course::findOrFail($courseId);

        $completedAt = $course->completedByUserAt($userId);

        if (! $completedAt && ! (auth()->user() && auth()->user()->hasRole('Admin'))) {
            abort(403, __('Course is not completed'));
        }

        return view('filament-lms::certificates.default')
            ->with('dateEarned', Carbon::parse($completedAt)->format(('F j, Y')))
            ->with('user', User::find($userId))
            ->with('course', $course);
    }

    public function download(Course $course): StreamedResponse
    {
        if (! auth()->check()) {
            // TODO login
            return redirect()->route('login');
        }

        $url = URL::temporarySignedRoute(
            'filament-lms::certificates.show',
            now()->addMinutes(20),
            ['course' => $course, 'user' => auth()->user()->id]
        );

        $pdf = Browsershot::url($url)
            ->waitUntilNetworkIdle()
            ->showBackground()
            ->landscape()
            ->pdf();

        $filename = Str::slug($course->name).'-'.Str::slug(auth()->user()->name).'-certificate-'.now()->toDateString().'.pdf';

        return response()->stream(function () use ($pdf) {
            echo $pdf;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }
}
