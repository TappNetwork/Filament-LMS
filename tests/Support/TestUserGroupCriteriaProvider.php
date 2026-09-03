<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Support;

use Filament\QueryBuilder\Constraints\TextConstraint;
use Tapp\FilamentLms\UserGroups\Contracts\UserGroupCriteriaProvider;
use Tapp\FilamentLms\UserGroups\CriteriaSource;

final class TestUserGroupCriteriaProvider implements UserGroupCriteriaProvider
{
    public function sources(): array
    {
        return [
            'user' => new CriteriaSource(
                key: 'user',
                label: 'User',
                constraints: fn (): array => [
                    TextConstraint::make('name')->label('Name'),
                    TextConstraint::make('email')->label('Email'),
                ],
            ),
        ];
    }
}
