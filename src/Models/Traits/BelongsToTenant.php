<?php

namespace Tapp\FilamentLms\Models\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
        // Use configured relationship name if provided
        if ($relationshipName = config('filament-lms.tenancy.relationship_name')) {
            return $relationshipName;
        }

        // Auto-detect from tenant model class name
        $tenantModel = config('filament-lms.tenancy.model');

        if (! $tenantModel) {
            if (config('filament-lms.tenancy.enabled')) {
                throw new \Exception('Tenant model not configured in filament-lms.tenancy.model');
            }

            return 'tenant'; // Return a default value when tenancy is disabled
        }

        return Str::snake(class_basename($tenantModel));
    }

    /**
     * Get the tenant column name.
     */
    public static function getTenantColumnName(): string
    {
        // Use configured column name if provided
        if ($columnName = config('filament-lms.tenancy.column')) {
            return $columnName;
        }

        // Auto-detect from tenant model class name
        return static::getTenantRelationshipName().'_id';
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
}
