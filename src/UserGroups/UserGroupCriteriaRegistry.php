<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\UserGroups;

use InvalidArgumentException;
use Tapp\FilamentLms\UserGroups\Contracts\UserGroupCriteriaProvider;

final class UserGroupCriteriaRegistry
{
    public function enabled(): bool
    {
        return $this->provider() !== null;
    }

    public function provider(): ?UserGroupCriteriaProvider
    {
        $provider = config('filament-lms.user_groups.criteria_provider');

        if ($provider === null || $provider === '') {
            return null;
        }

        if (is_string($provider)) {
            $provider = app($provider);
        }

        if (! $provider instanceof UserGroupCriteriaProvider) {
            throw new InvalidArgumentException(
                'filament-lms.user_groups.criteria_provider must implement '.UserGroupCriteriaProvider::class
            );
        }

        return $provider;
    }

    /**
     * @return array<string, CriteriaSource>
     */
    public function sources(): array
    {
        $provider = $this->provider();

        if ($provider === null) {
            return [];
        }

        $sources = $provider->sources();
        $keyed = [];

        foreach ($sources as $key => $source) {
            if ($key !== $source->key) {
                throw new InvalidArgumentException("Criteria source key mismatch for [{$source->key}].");
            }

            if (isset($keyed[$key])) {
                throw new InvalidArgumentException("Duplicate criteria source key [{$key}].");
            }

            $keyed[$key] = $source;
        }

        return $keyed;
    }

    public function source(string $key): CriteriaSource
    {
        $sources = $this->sources();

        if (! isset($sources[$key])) {
            throw new InvalidArgumentException("Unknown user group criteria source [{$key}].");
        }

        return $sources[$key];
    }

    /**
     * @return array<string, string>
     */
    public function sourceOptions(): array
    {
        return collect($this->sources())
            ->mapWithKeys(fn (CriteriaSource $source): array => [$source->key => $source->label])
            ->all();
    }
}
