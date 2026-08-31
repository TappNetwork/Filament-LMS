<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups;

use Closure;
use Filament\QueryBuilder\Constraints\Constraint;
use InvalidArgumentException;

final class CriteriaSource
{
    /**
     * @param  list<Constraint>|Closure(): list<Constraint>  $constraints
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array|Closure $constraints,
        public readonly ?string $userRelationship = null,
        public readonly ?string $icon = null,
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Criteria source key cannot be empty.');
        }
    }

    /**
     * @return list<Constraint>
     */
    public function getConstraints(): array
    {
        $constraints = $this->constraints instanceof Closure
            ? ($this->constraints)()
            : $this->constraints;

        return array_values($constraints);
    }

    public function isUserSource(): bool
    {
        return $this->userRelationship === null;
    }
}
