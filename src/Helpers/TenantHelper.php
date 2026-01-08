<?php

namespace Tapp\FilamentLms\Helpers;

use Illuminate\Support\Str;

class TenantHelper
{
    /**
     * Get the tenant relationship name from config or derive from model.
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
     * Get the tenant column name from config or derive from relationship name.
     */
    public static function getTenantColumnName(): string
    {
        // Use configured column name if provided
        if ($columnName = config('filament-lms.tenancy.column')) {
            return $columnName;
        }

        // Auto-detect from relationship name
        return static::getTenantRelationshipName().'_id';
    }

    /**
     * Get the tenant table name from the configured model.
     */
    public static function getTenantTableName(): string
    {
        $tenantModel = config('filament-lms.tenancy.model');

        if (! $tenantModel) {
            throw new \Exception('Tenant model not configured in filament-lms.tenancy.model');
        }

        return (new $tenantModel)->getTable();
    }
}
