<?php

namespace Tapp\FilamentLms\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\CertificateBuilder;

class CertificateController extends Controller
{
    public function show($courseId, $userId): View
    {
        $course = Course::findOrFail($courseId);

        $userModel = config('auth.providers.users.model');

        if (! $userModel) {
            throw new InvalidArgumentException('User model not configured');
        }

        $user = $userModel::findOrFail($userId);

        if (! $user instanceof Authenticatable) {
            throw new InvalidArgumentException('User model must implement Authenticatable contract');
        }

        if (! request()->hasValidSignature() &&
            ! $course->completedByUserAt($userId) &&
            ! Auth::user()?->can('update', $course)) {
            abort(403);
        }

        $builderView = $this->builderCertificateView($course, $user);

        if ($builderView === null) {
            abort(404);
        }

        return $builderView;
    }

    public function download(Course $course): StreamedResponse
    {
        if (! $course->completedByUserAt(Auth::id()) && ! Auth::user()?->can('update', $course)) {
            abort(403);
        }

        $url = URL::temporarySignedRoute(
            'filament-lms::certificates.show',
            now()->addMinutes(20),
            ['course' => $course, 'user' => Auth::id()]
        );

        $pdf = $this->browsershot($url)->pdf();

        $filename = Str::slug($course->name).'-'.Str::slug(Auth::user()->name).'-certificate-'.now()->toDateString().'.pdf';

        return response()->stream(function () use ($pdf) {
            echo $pdf;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    public function browsershot(string $url): Browsershot
    {
        return Browsershot::url($url)
            ->noSandbox()
            ->waitUntilNetworkIdle()
            ->showBackground()
            ->landscape();
    }

    private function builderCertificateView(Course $course, Authenticatable $user): ?View
    {
        if (! CertificateBuilder::enabled() || $course->certificate_template_id === null) {
            return null;
        }

        $templateClass = CertificateBuilder::TEMPLATE_MODEL;
        $template = $templateClass::query()->find($course->certificate_template_id);

        if ($template === null) {
            return null;
        }

        return view('filament-certificate-builder::certificate', [
            'template' => $template,
            'tokens' => $this->tokensForTemplate($template, $course, $user),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function tokensForTemplate(object $template, Course $course, Authenticatable $user): array
    {
        $layoutClass = CertificateBuilder::LAYOUT_CLASS;
        $resolves = CertificateBuilder::RESOLVES_TOKENS;

        if (! class_exists($layoutClass)) {
            return [];
        }

        $tokenSet = method_exists($template, 'tokenSet')
            ? $template->tokenSet()
            : CertificateBuilder::tokenSet();

        $resolver = app($layoutClass::resolverClass($tokenSet));

        if (! is_object($resolver) || ! $resolver instanceof $resolves) {
            return $layoutClass::sampleTokens($tokenSet);
        }

        return $resolver->resolve([
            'course' => $course,
            'user' => $user,
        ]);
    }
}
