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
use Tapp\FilamentLms\Policies\CertificatePolicy;

class CertificateController extends Controller
{
    public function __construct(
        protected CertificatePolicy $policy
    ) {}

    // TODO does this need authentication to prevent direct visits?
    public function show($courseId, $userId): View
    {
        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        if (! request()->hasValidSignature() && ! $this->policy->view(auth()->user(), $course, $user)) {
            abort(403);
        }

        $view = 'filament-lms::certificates.'.$course->award;

        if (! view()->exists($view)) {
            $view = 'filament-lms::certificates.default';
        }

        $completedAt = $course->completedByUserAt($userId);

        return view($view)
            ->with('dateEarned', $completedAt ? Carbon::parse($completedAt)->format(('F j, Y')) : null)
            ->with('user', $user)
            ->with('course', $course);
    }

    public function download(Course $course): StreamedResponse
    {
        if (! $this->policy->view(auth()->user(), $course)) {
            abort(403);
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
