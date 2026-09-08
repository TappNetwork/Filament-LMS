<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Support;

use Tapp\FilamentLms\Models\Course;

final class CertificateBuilder
{
    public const TEMPLATE_MODEL = 'Tapp\\FilamentCertificateBuilder\\Models\\CertificateTemplate';

    public const LAYOUT_CLASS = 'Tapp\\FilamentCertificateBuilder\\Support\\CertificateLayout';

    public const RESOLVES_TOKENS = 'Tapp\\FilamentCertificateBuilder\\Contracts\\ResolvesCertificateTokens';

    public static function enabled(): bool
    {
        return (bool) config('filament-lms.integrations.certificate_builder.enabled', false)
            && class_exists(self::TEMPLATE_MODEL);
    }

    public static function canCreateTemplate(?object $user, Course $course): bool
    {
        return self::canManageTemplates($user, $course)
            && self::assignedTemplateId($course) === null;
    }

    public static function canEditTemplate(?object $user, Course $course): bool
    {
        return self::canManageTemplates($user, $course)
            && self::templateEditUrl($course) !== null;
    }

    public static function canManageTemplates(?object $user, Course $course): bool
    {
        return self::enabled()
            && $user !== null
            && method_exists($user, 'can')
            && $user->can('update', $course) === true;
    }

    public static function assignedTemplateId(Course $course): int|string|null
    {
        $templateId = $course->certificate_template_id;

        if ($templateId === null || ! class_exists(self::TEMPLATE_MODEL)) {
            return null;
        }

        $exists = self::TEMPLATE_MODEL::query()->whereKey($templateId)->exists();

        return $exists ? $templateId : null;
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
