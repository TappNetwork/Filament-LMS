<?php

namespace Tapp\FilamentLms\Contracts;

use Tapp\FilamentLms\Models\Step;

/**
 * Interface for User models that use the FilamentLmsUser trait.
 * This interface helps PHPStan understand that users have the canAccessStep method.
 */
interface FilamentLmsUserInterface
{
    /**
     * Determine if the user can access a specific step.
     * This method is provided by the FilamentLmsUser trait.
     *
     * @param  Step  $step  The step to check access for
     * @return bool True if the user can access the step, false otherwise
     */
    public function canAccessStep(Step $step): bool;
}
