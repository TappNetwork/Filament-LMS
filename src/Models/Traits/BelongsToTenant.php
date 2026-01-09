<?php

namespace Tapp\FilamentLms\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tapp\FilamentLms\Helpers\TenantHelper;
use Tapp\FilamentLms\Models\Scopes\TenantScope;

trait BelongsToTenant
{
    /**
     * Boot the trait and register the dynamic tenant relationship.
     */
    public static function bootBelongsToTenant(): void
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return;
        }

        // Add global scope to filter all queries by current tenant
        // This protects direct Eloquent queries made outside of Filament Resources
        // (e.g., in LMS Pages like Step, Dashboard, CourseCompleted)
        // Filament's own tenant scope only applies to Resource queries
        $scopeName = 'filament_lms_tenancy';

        if (! static::hasGlobalScope($scopeName)) {
            static::addGlobalScope($scopeName, new TenantScope);
        }

        // Register the dynamic relationship
        static::resolveRelationUsing(
            static::getTenantRelationshipName(),
            function ($model) {
                return $model->belongsTo(config('filament-lms.tenancy.model'), static::getTenantColumnName());
            }
        );

        static::creating(function ($model) {
            $tenantColumnName = static::getTenantColumnName();

            // Skip if tenant foreign key is already set (e.g., by Filament's observer)
            if (! empty($model->{$tenantColumnName})) {
                return;
            }

            $tenantRelationshipName = static::getTenantRelationshipName();

            // Try to get tenant from Filament context (Filament's standard method)
            // This handles top-level resources created outside Filament's Resource observers
            if (class_exists(\Filament\Facades\Filament::class)) {
                $tenant = \Filament\Facades\Filament::getTenant();
                if ($tenant) {
                    $model->{$tenantRelationshipName}()->associate($tenant);

                    return;
                }
            }

            // For Lesson, get tenant from its Course
            if (method_exists($model, 'course') && isset($model->course_id)) {
                $parentCourseId = $model->course_id;
                $parentCourseClass = get_class($model->course()->getRelated());
                $parentCourse = $parentCourseClass::find($parentCourseId);

                if ($parentCourse) {
                    $parentTenant = $parentCourse->{$tenantRelationshipName};
                    if ($parentTenant) {
                        $model->{$tenantRelationshipName}()->associate($parentTenant);

                        return;
                    }
                }
            }

            // For Step, get tenant from its Lesson
            if (method_exists($model, 'lesson') && isset($model->lesson_id)) {
                $parentLessonId = $model->lesson_id;
                $parentLessonClass = get_class($model->lesson()->getRelated());
                $parentLesson = $parentLessonClass::find($parentLessonId);

                if ($parentLesson) {
                    $parentTenant = $parentLesson->{$tenantRelationshipName};
                    if ($parentTenant) {
                        $model->{$tenantRelationshipName}()->associate($parentTenant);

                        return;
                    }
                }
            }

            // For content types (Document, Video, etc.), get tenant from their Step
            if (method_exists($model, 'step') && isset($model->step_id)) {
                $parentStepId = $model->step_id;
                $parentStepClass = get_class($model->step()->getRelated());
                $parentStep = $parentStepClass::find($parentStepId);

                if ($parentStep) {
                    $parentTenant = $parentStep->{$tenantRelationshipName};
                    if ($parentTenant) {
                        $model->{$tenantRelationshipName}()->associate($parentTenant);
                    }
                }
            }
        });
    }

    /**
     * Get the tenant relationship name.
     */
    public static function getTenantRelationshipName(): string
    {
        return TenantHelper::getTenantRelationshipName();
    }

    /**
     * Get the tenant column name.
     */
    public static function getTenantColumnName(): string
    {
        return TenantHelper::getTenantColumnName();
    }

    /**
     * Get the tenant relationship instance.
     * This provides a typed method for IDEs and static analysis.
     */
    public function tenant(): ?BelongsTo
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return null;
        }

        $tenantModel = config('filament-lms.tenancy.model');

        if (! $tenantModel) {
            throw new \Exception('Tenant model not configured in filament-lms.tenancy.model');
        }

        return $this->belongsTo($tenantModel, static::getTenantColumnName());
    }

    /**
     * Scope a query to include all tenants (bypass tenant filtering).
     * Useful for administrative operations.
     */
    public function scopeWithoutTenantScope($query)
    {
        return $query->withoutGlobalScope('filament_lms_tenancy');
    }

    /**
     * Scope a query to a specific tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        $tenantColumnName = static::getTenantColumnName();

        return $query->withoutGlobalScope('filament_lms_tenancy')
            ->where($tenantColumnName, $tenantId);
    }
}
