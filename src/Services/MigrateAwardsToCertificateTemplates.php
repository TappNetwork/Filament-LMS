<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Support\AwardCertificateBlueprint;
use Tapp\FilamentLms\Support\AwardCertificateBlueprintFactory;
use Tapp\FilamentLms\Support\AwardCertificateLayoutApplier;
use Tapp\FilamentLms\Support\CertificateBuilder;

final class MigrateAwardsToCertificateTemplates
{
    public function __construct(
        private AwardCertificateBlueprintFactory $blueprints,
        private AwardCertificateLayoutApplier $layouts,
    ) {}

    /**
     * @param  class-string<Model>|null  $templateClass
     * @param  class-string|null  $layoutClass
     * @return array{
     *     templates_created: int,
     *     templates_reused: int,
     *     courses_updated: int,
     *     logos_attached: int,
     *     awards: list<array{
     *         award: string,
     *         template: string,
     *         created: bool,
     *         courses: int,
     *         logos: int
     *     }>
     * }
     */
    public function handle(
        ?string $award = null,
        bool $dryRun = false,
        bool $force = false,
        ?string $templateClass = null,
        ?string $layoutClass = null,
    ): array {
        $templateClass ??= CertificateBuilder::TEMPLATE_MODEL;
        $layoutClass ??= CertificateBuilder::LAYOUT_CLASS;

        if (! class_exists($templateClass)) {
            throw new RuntimeException('Certificate builder is not installed.');
        }

        $tokenSet = CertificateBuilder::tokenSet();
        $summary = [
            'templates_created' => 0,
            'templates_reused' => 0,
            'courses_updated' => 0,
            'logos_attached' => 0,
            'awards' => [],
        ];

        foreach ($this->blueprints->awardKeys($award) as $awardKey) {
            $blueprint = $this->blueprints->make($awardKey);
            $existing = $templateClass::query()
                ->where('name', $blueprint->templateName)
                ->first();

            $created = $existing === null;
            $logoCount = 0;
            $courseCount = $this->coursesToUpdate($awardKey, $force)->count();

            if (! $dryRun) {
                $template = $existing ?? $templateClass::query()->create([
                    'name' => $blueprint->templateName,
                    'token_set' => $tokenSet,
                    'layout' => $this->layoutFor($blueprint, $layoutClass, $tokenSet),
                ]);

                if ($existing !== null && $force) {
                    $template->update([
                        'token_set' => $tokenSet,
                        'layout' => $this->layoutFor($blueprint, $layoutClass, $tokenSet),
                    ]);
                }

                $logoCount = $this->attachLogos($template, $blueprint, $force);
                $courseCount = $this->coursesToUpdate($awardKey, $force)->update([
                    'certificate_template_id' => $template->getKey(),
                ]);
            }

            $summary['templates_created'] += $created ? 1 : 0;
            $summary['templates_reused'] += $created ? 0 : 1;
            $summary['courses_updated'] += $courseCount;
            $summary['logos_attached'] += $logoCount;
            $summary['awards'][] = [
                'award' => $awardKey,
                'template' => $blueprint->templateName,
                'created' => $created,
                'courses' => $courseCount,
                'logos' => $logoCount,
            ];
        }

        return $summary;
    }

    /**
     * @return Builder<Course>
     */
    private function coursesToUpdate(string $awardKey, bool $force): Builder
    {
        $query = Course::query()
            ->withoutTenantScope()
            ->where('award', $awardKey);

        if (! $force) {
            $query->whereNull('certificate_template_id');
        }

        return $query;
    }

    /**
     * @param  class-string|null  $layoutClass
     * @return array<string, mixed>
     */
    private function layoutFor(AwardCertificateBlueprint $blueprint, ?string $layoutClass, string $tokenSet): array
    {
        $layout = class_exists((string) $layoutClass) && method_exists((string) $layoutClass, 'default')
            ? $layoutClass::default($tokenSet)
            : [
                'width' => 1050,
                'height' => 774,
                'signature_count' => 2,
                'elements' => [],
            ];

        if (! is_array($layout)) {
            $layout = [];
        }

        return $this->layouts->apply($layout, $blueprint);
    }

    private function attachLogos(Model $template, AwardCertificateBlueprint $blueprint, bool $force): int
    {
        if (! method_exists($template, 'addMedia')) {
            return 0;
        }

        $attached = 0;

        foreach (array_slice($blueprint->logoPaths, 0, 3) as $index => $path) {
            $absolutePath = $this->blueprints->resolveLogoAbsolutePath($path);
            $collection = 'logo_'.($index + 1);

            if ($absolutePath === null) {
                continue;
            }

            if (! $force && method_exists($template, 'getFirstMedia') && $template->getFirstMedia($collection) !== null) {
                continue;
            }

            $template->addMedia($absolutePath)
                ->preservingOriginal()
                ->toMediaCollection($collection);

            $attached++;
        }

        return $attached;
    }
}
