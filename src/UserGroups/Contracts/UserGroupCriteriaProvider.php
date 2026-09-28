<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups\Contracts;

use Tapp\FilamentLms\UserGroups\CriteriaSource;

interface UserGroupCriteriaProvider
{
    /**
     * Named criteria sources available when building user groups.
     * Must include only allow-listed fields/constraints — never arbitrary columns.
     *
     * @return array<string, CriteriaSource>
     */
    public function sources(): array;
}
