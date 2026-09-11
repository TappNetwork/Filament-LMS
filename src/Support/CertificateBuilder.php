<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Illuminate\Support\Facades\Gate;
use Tapp\FilamentLms\Models\Course;

final class CertificateBuilder
{
    public const TEMPLATE_MODEL = 'Tapp\\FilamentCertificateBuilder\\Models\\CertificateTemplate';

    public const LAYOUT_CLASS = 'Tapp\\FilamentCertificateBuilder\\Support\\CertificateLayout';

    public const RESOLVES_TOKENS = 'Tapp\\FilamentCertificateBuilder\\Contracts\\ResolvesCertificateTokens';

    public static function enabled(): bool
    {
        return class_exists(self::TEMPLATE_MODEL);
    }

    public static function canCreateTemplate(?object $user, Course $course): bool
    {
        return self::canManageTemplates($user, $course);
    }

    public static function defaultTemplateId(): ?int
    {
        if (! class_exists(self::TEMPLATE_MODEL)) {
            return null;
        }

        $tokenSet = self::tokenSet();
        $query = self::TEMPLATE_MODEL::query()->where('token_set', $tokenSet);
        $template = (clone $query)->where('name', 'Default Certificate')->first()
            ?? $query->orderBy('name')->first();

        if ($template !== null) {
            return (int) $template->getKey();
        }

        $layoutClass = self::LAYOUT_CLASS;
        $layout = class_exists($layoutClass) && method_exists($layoutClass, 'default')
            ? $layoutClass::default($tokenSet)
            : [];

        $created = self::TEMPLATE_MODEL::query()->create([
            'name' => 'Default Certificate',
            'token_set' => $tokenSet,
            'layout' => is_array($layout) ? $layout : [],
        ]);

        return (int) $created->getKey();
    }

    public static function canEditTemplate(?object $user, Course $course): bool
    {
        return self::canManageTemplates($user, $course)
            && self::templateEditUrl($course) !== null;
    }

    public static function canManageTemplates(?object $user, Course $course): bool
    {
        return self::enabled()
            && self::userCanUpdateCourse($user, $course);
    }

    public static function userCanUpdateCourse(?object $user, Course $course): bool
    {
        if ($user === null) {
            return false;
        }

        $policy = Gate::getPolicyFor($course);

        if ($policy === null) {
            return true;
        }

        $policyClass = is_object($policy) ? $policy::class : $policy;

        if (! method_exists($policyClass, 'update')) {
            return true;
        }

        return method_exists($user, 'can') && $user->can('update', $course) === true;
    }

    public static function assignedTemplateId(Course $course): ?int
    {
        $templateId = $course->certificate_template_id;

        if ($templateId === null || ! class_exists(self::TEMPLATE_MODEL)) {
            return null;
        }

        $exists = self::TEMPLATE_MODEL::query()->whereKey($templateId)->exists();

        return $exists ? (int) $templateId : null;
    }

    public static function templateEditUrl(Course $course): ?string
    {
        $resource = self::templateResource();
        $templateId = self::assignedTemplateId($course);

        if ($resource === null || $templateId === null) {
            return null;
        }

        return $resource::getUrl('edit', ['record' => $templateId]);
    }

    public static function tokenSet(): string
    {
        $tokenSet = config('filament-lms.integrations.certificate_builder.token_set', 'course');

        return is_string($tokenSet) && $tokenSet !== '' ? $tokenSet : 'course';
    }

    /**
     * @return class-string|null
     */
    public static function templateResource(): ?string
    {
        $resource = config('filament-lms.integrations.certificate_builder.template_resource');

        if (! is_string($resource) || $resource === '' || ! class_exists($resource)) {
            return null;
        }

        return $resource;
    }
}
