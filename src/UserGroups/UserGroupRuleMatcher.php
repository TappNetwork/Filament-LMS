<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups;

use Filament\QueryBuilder\Constraints\Constraint;
use Filament\QueryBuilder\Forms\Components\RuleBuilder;
use Filament\QueryBuilder\Models\Scopes\QueryBuilderScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use InvalidArgumentException;

final class UserGroupRuleMatcher
{
    public function __construct(
        private readonly UserGroupCriteriaRegistry $registry,
    ) {}

    /**
     * Normalize and validate persisted or form rule payloads.
     *
     * @param  array<string, mixed>  $rules
     * @return array{version: int, sources: list<array{source: string, rules: array<string, mixed>}>}
     */
    public function normalize(array $rules): array
    {
        $version = (int) ($rules['version'] ?? 1);
        $sourcesPayload = $rules['sources'] ?? [];

        if (! is_array($sourcesPayload)) {
            throw new InvalidArgumentException('User group rules sources must be an array.');
        }

        $maxRules = (int) config('filament-lms.user_groups.max_rules', 100);
        $maxNestingDepth = (int) config('filament-lms.user_groups.max_nesting_depth', 10);
        $normalizedSources = [];
        $totalRules = 0;

        foreach ($sourcesPayload as $sourcePayload) {
            if (! is_array($sourcePayload)) {
                continue;
            }

            $sourceKey = $sourcePayload['source'] ?? null;

            if (! is_string($sourceKey) || $sourceKey === '') {
                throw new InvalidArgumentException('Each criteria section must select a configured source.');
            }

            $source = $this->registry->source($sourceKey);
            $sourceRules = $sourcePayload['rules'] ?? [];

            if (! is_array($sourceRules)) {
                throw new InvalidArgumentException("Rules for source [{$sourceKey}] must be an array.");
            }

            $sourceRules = $this->filterEmptyRules($sourceRules);
            $this->assertRulesUseAllowedConstraints($sourceRules, $source);
            $this->assertRuleLimits($sourceRules, $maxRules, $maxNestingDepth, $totalRules);

            if ($sourceRules === []) {
                continue;
            }

            $normalizedSources[] = [
                'source' => $sourceKey,
                'rules' => $sourceRules,
            ];
        }

        return [
            'version' => $version,
            'sources' => array_values($normalizedSources),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function matchingUsersQuery(array $rules): Builder
    {
        $normalized = $this->normalize($rules);
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-lms.user_model');
        $query = $userModel::query();

        if ($normalized['sources'] === []) {
            return $query->whereRaw('0 = 1');
        }

        foreach ($normalized['sources'] as $sourcePayload) {
            $source = $this->registry->source($sourcePayload['source']);
            $constraints = $source->getConstraints();
            $sourceRules = $sourcePayload['rules'];

            if ($source->isUserSource()) {
                $scope = QueryBuilderScope::make($sourceRules, $constraints);
                $scope($query);

                continue;
            }

            $relationship = $source->userRelationship;

            if ($relationship === null || $relationship === '') {
                throw new InvalidArgumentException("Criteria source [{$source->key}] is missing a user relationship.");
            }

            $query->whereHas($relationship, function (Builder $relatedQuery) use ($sourceRules, $constraints): void {
                $scope = QueryBuilderScope::make($sourceRules, $constraints);
                $scope($relatedQuery);
            });
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    public function userMatches(Model $user, array $rules): bool
    {
        return $this->matchingUsersQuery($rules)
            ->whereKey($user->getKey())
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return list<string>
     */
    public function summarize(array $rules): array
    {
        $normalized = $this->normalize($rules);
        $summaries = [];

        foreach ($normalized['sources'] as $sourcePayload) {
            $source = $this->registry->source($sourcePayload['source']);
            $constraints = Arr::mapWithKeys(
                $source->getConstraints(),
                fn (Constraint $constraint): array => [$constraint->getName() => $constraint],
            );

            foreach ($sourcePayload['rules'] as $rule) {
                if (($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                    $summaries[] = $source->label.': OR group';

                    continue;
                }

                $constraint = $constraints[$rule['type'] ?? ''] ?? null;

                if ($constraint === null) {
                    continue;
                }

                $operatorString = $rule['data'][$constraint::OPERATOR_SELECT_NAME] ?? null;

                if (! is_string($operatorString) || $operatorString === '') {
                    continue;
                }

                [$operatorName, $isInverse] = $constraint->parseOperatorString($operatorString);
                $operator = $constraint->getOperator($operatorName);

                if ($operator === null) {
                    continue;
                }

                $settings = $rule['data']['settings'] ?? [];
                $constraint->settings($settings)->inverse($isInverse);
                $operator->constraint($constraint)->settings($settings)->inverse($isInverse);

                $summaries[] = $source->label.': '.$operator->getSummary($isInverse);

                $constraint->settings(null)->inverse(null);
                $operator->constraint(null)->settings(null)->inverse(null);
            }
        }

        return $summaries;
    }

    /**
     * @param  array<int|string, mixed>  $rules
     * @return array<int|string, mixed>
     */
    private function filterEmptyRules(array $rules): array
    {
        $filtered = [];

        foreach ($rules as $key => $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                $groups = $rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? [];
                $filteredGroups = [];

                foreach ($groups as $groupKey => $group) {
                    if (! is_array($group)) {
                        continue;
                    }

                    $groupRules = $this->filterEmptyRules($group['rules'] ?? []);

                    if ($groupRules === []) {
                        continue;
                    }

                    $filteredGroups[$groupKey] = [
                        ...$group,
                        'rules' => $groupRules,
                    ];
                }

                if ($filteredGroups === []) {
                    continue;
                }

                $filtered[$key] = [
                    ...$rule,
                    'data' => [
                        ...($rule['data'] ?? []),
                        RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME => $filteredGroups,
                    ],
                ];

                continue;
            }

            $operator = $rule['data'][Constraint::OPERATOR_SELECT_NAME] ?? null;

            if (blank($operator)) {
                continue;
            }

            $filtered[$key] = $rule;
        }

        return $filtered;
    }

    /**
     * @param  array<int|string, mixed>  $rules
     */
    private function assertRulesUseAllowedConstraints(array $rules, CriteriaSource $source): void
    {
        $allowed = Arr::mapWithKeys(
            $source->getConstraints(),
            fn (Constraint $constraint): array => [$constraint->getName() => true],
        );

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $type = $rule['type'] ?? null;

            if ($type === RuleBuilder::OR_BLOCK_NAME) {
                foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? [] as $group) {
                    $this->assertRulesUseAllowedConstraints($group['rules'] ?? [], $source);
                }

                continue;
            }

            if (! is_string($type) || ! isset($allowed[$type])) {
                throw new InvalidArgumentException("Constraint [{$type}] is not allowed for source [{$source->key}].");
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rules
     */
    private function assertRuleLimits(array $rules, int $maxRules, int $maxNestingDepth, int &$totalRules, int $depth = 0): void
    {
        if ($depth > $maxNestingDepth) {
            throw new InvalidArgumentException("User group rules exceed max nesting depth of {$maxNestingDepth}.");
        }

        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            if (($rule['type'] ?? null) === RuleBuilder::OR_BLOCK_NAME) {
                foreach ($rule['data'][RuleBuilder::OR_BLOCK_GROUPS_REPEATER_NAME] ?? [] as $group) {
                    $this->assertRuleLimits($group['rules'] ?? [], $maxRules, $maxNestingDepth, $totalRules, $depth + 1);
                }

                continue;
            }

            $totalRules++;

            if ($totalRules > $maxRules) {
                throw new InvalidArgumentException("User group rules exceed max rule count of {$maxRules}.");
            }
        }
    }
}
