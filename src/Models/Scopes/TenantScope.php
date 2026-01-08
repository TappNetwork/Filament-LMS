<?php

namespace Tapp\FilamentLms\Models\Scopes;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Tapp\FilamentLms\Helpers\TenantHelper;

class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * This scope filters all LMS model queries by the current tenant.
     * We've disabled Filament's Resource-level tenant scoping (isScopedToTenant = false)
     * in all LMS Resources to use this unified model-level scope instead.
     * This ensures consistent tenant filtering across both Resource queries
     * and direct Eloquent queries (e.g., in LMS Pages).
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (! config('filament-lms.tenancy.enabled')) {
            return;
        }

        $tenant = Filament::getTenant();

        if (! $tenant) {
            return;
        }

        $tenantColumnName = TenantHelper::getTenantColumnName();

        $builder->where($model->qualifyColumn($tenantColumnName), $tenant->getKey());
    }
}
