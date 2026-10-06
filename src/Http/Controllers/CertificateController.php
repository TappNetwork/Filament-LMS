<?php

namespace Tapp\FilamentLms\Http\Controllers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\CertificatePdf\CertificatePdfGenerator;
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

        // HTML certificate is private: owner or course updater only (no guest / completion-only access).
        if (Auth::id() != $userId && ! Auth::user()?->can('update', $course)) {
            abort(403);
        }

        $builderView = $this->builderCertificateView($course, $user);

        if ($builderView === null) {
            abort(404);
        }

        return $builderView;
    }

    public function download(Course $course, CertificatePdfGenerator $pdfs): StreamedResponse
    {
        if (! $course->completedByUserAt(Auth::id()) && ! Auth::user()?->can('update', $course)) {
            abort(403);
        }

        /** @var Authenticatable $user */
        $user = Auth::user();

        $builderView = $this->builderCertificateView($course, $user);

        if ($builderView === null) {
            abort(404);
        }

        $pdf = $pdfs->pdf($this->htmlForPdf($builderView));

        $filename = Str::slug($course->name).'-'.Str::slug((string) data_get($user, 'name', 'user')).'-certificate-'.now()->toDateString().'.pdf';

        return response()->stream(function () use ($pdf) {
            echo $pdf;
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$filename,
        ]);
    }

    /**
     * Render the same certificate Blade used by the in-browser show route,
     * with a base href so relative CSS/assets resolve in headless PDF renderers.
     */
    public function htmlForPdf(View $view): string
    {
        $html = $view->render();
        $base = rtrim((string) config('app.url'), '/').'/';
        $baseTag = '<base href="'.e($base).'">';

        if (preg_match('/<head([^>]*)>/i', $html) === 1) {
            return (string) preg_replace('/<head([^>]*)>/i', '<head$1>'.$baseTag, $html, 1);
        }

        return $baseTag.$html;
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
