<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\RelationManagers;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\QueryBuilder\Constraints\Constraint;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\UserGroup;
use Tapp\FilamentLms\UserGroups\CriteriaSource;
use Tapp\FilamentLms\UserGroups\UserGroupCriteriaRegistry;
use Tapp\FilamentLms\UserGroups\UserGroupMembershipSynchronizer;
use Tapp\FilamentLms\UserGroups\UserGroupRuleMatcher;

class CourseUserGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'userGroups';

    protected static ?string $title = 'Assigned User Groups';

    protected static ?string $modelLabel = 'User Group';

    protected static ?string $pluralModelLabel = 'User Groups';

    public ?string $activeGroupId = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return app(UserGroupCriteriaRegistry::class)->enabled();
    }

    public function mount(): void
    {
        parent::mount();

        /** @var Course $course */
        $course = $this->getOwnerRecord();
        $defaultGroupId = $course->defaultUserGroup()?->id;
        $this->activeGroupId = $defaultGroupId !== null ? (string) $defaultGroupId : null;
        $this->loadActiveGroupIntoFilters();
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        $displayColumns = config('filament-lms.user_groups.display_columns', ['name', 'email']);

        return $table
            ->query(fn (): Builder => $this->resolveMatchingUsersQuery())
            ->columns($this->userTableColumns($displayColumns))
            ->heading('Assigned User Groups')
            ->description(fn (): View => view('filament-lms::filament.course-user-groups-description', [
                'groupOptions' => $this->attachedGroupOptions(),
            ]))
            ->filters($this->criteriaFilters(), layout: FiltersLayout::AboveContent)
            ->deferFilters()
            ->headerActions([
                Action::make('saveGroup')
                    ->label(fn (): string => $this->resolvedActiveGroupId() !== null ? 'Update group' : 'Save as group')
                    ->color('primary')
                    ->form([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->default(fn (): ?string => $this->activeGroup()?->name),
                        TextInput::make('description')
                            ->maxLength(1000)
                            ->default(fn (): ?string => $this->activeGroup()?->description),
                    ])
                    ->action(function (array $data): void {
                        $this->saveActiveGroup($data);
                        $this->refreshMatchingUsersTable();
                    }),
                Action::make('removeGroup')
                    ->label('Remove group')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => $this->resolvedActiveGroupId() !== null)
                    ->action(function (): void {
                        $this->removeActiveGroup();
                        $this->refreshMatchingUsersTable();
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->emptyStateHeading('No matching users')
            ->emptyStateDescription('Add criteria above and apply filters to preview matching users, then save as a group.');
    }

    /**
     * @param  list<string>  $displayColumns
     * @return array<int, TextColumn>
     */
    protected function userTableColumns(array $displayColumns): array
    {
        $columns = [];

        foreach ($displayColumns as $column) {
            $columns[] = TextColumn::make($column)
                ->label(str($column)->replace('_', ' ')->title()->toString())
                ->searchable()
                ->sortable();
        }

        if ($columns === []) {
            $columns[] = TextColumn::make('email')->searchable()->sortable();
        }

        return $columns;
    }

    /**
     * One official QueryBuilder filter per criteria source.
     *
     * Nested custom RuleBuilders (inside Form Builder / Repeater) break Filament
     * schema container initialization; Tables\Filters\QueryBuilder is the supported path.
     *
     * @return list<QueryBuilder>
     */
    protected function criteriaFilters(): array
    {
        $registry = app(UserGroupCriteriaRegistry::class);
        $maxRules = (int) config('filament-lms.user_groups.max_rules', 100);
        $maxNestingDepth = (int) config('filament-lms.user_groups.max_nesting_depth', 10);
        $filters = [];

        foreach ($registry->sources() as $source) {
            $filters[] = QueryBuilder::make($source->key)
                ->label($source->label)
                ->constraints($this->freshConstraints($source))
                ->constraintPickerColumns(2)
                ->maxRules($maxRules)
                ->maxNestingDepth($maxNestingDepth)
                // Matching is applied in resolveMatchingUsersQuery() so related-model
                // sources can use whereHas and unsaved drafts can still preview.
                ->query(fn (Builder $query): Builder => $query)
                ->baseQuery(fn (Builder $query): Builder => $query)
                ->indicateUsing(function (array $state) use ($source): array {
                    try {
                        return app(UserGroupRuleMatcher::class)->summarize([
                            'version' => 1,
                            'sources' => [[
                                'source' => $source->key,
                                'rules' => is_array($state['rules'] ?? null) ? $state['rules'] : [],
                            ]],
                        ]);
                    } catch (\InvalidArgumentException) {
                        return [];
                    }
                });
        }

        return $filters;
    }

    /**
     * @return list<Constraint>
     */
    protected function freshConstraints(CriteriaSource $source): array
    {
        return $source->getConstraints();
    }

    protected function resolveMatchingUsersQuery(): Builder
    {
        /** @var class-string<Model> $userModel */
        $userModel = config('filament-lms.user_model');
        $keyName = (new $userModel)->getQualifiedKeyName();

        $sources = $this->activeFilterSources();

        if ($sources !== []) {
            try {
                return app(UserGroupRuleMatcher::class)->matchingUsersQuery([
                    'version' => 1,
                    'sources' => $sources,
                ]);
            } catch (\InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'mountedTableFilters' => $exception->getMessage(),
                ]);
            }
        }

        $group = $this->activeGroup();

        if ($group !== null && $group->published_revision > 0) {
            return $userModel::query()->whereIn(
                $keyName,
                $group->publishedMemberships()->select('user_id'),
            );
        }

        return $userModel::query()->whereRaw('0 = 1');
    }

    /**
     * @return list<array{source: string, rules: array<string, mixed>}>
     */
    protected function activeFilterSources(): array
    {
        try {
            return app(UserGroupRuleMatcher::class)->normalize([
                'version' => 1,
                'sources' => $this->normalizeFilterSources($this->tableFilters ?? []),
            ])['sources'];
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @return array<int|string, string>
     */
    protected function attachedGroupOptions(): array
    {
        /** @var Course $course */
        $course = $this->getOwnerRecord();

        return $course->userGroups()
            ->orderBy('lms_user_groups.name')
            ->get()
            ->mapWithKeys(function (UserGroup $group): array {
                $label = $group->name;

                if ((bool) $group->pivot?->is_default) {
                    $label .= ' (default)';
                }

                return [$group->id => $label];
            })
            ->all();
    }

    protected function resolvedActiveGroupId(): ?int
    {
        if (blank($this->activeGroupId)) {
            return null;
        }

        return (int) $this->activeGroupId;
    }

    protected function activeGroup(): ?UserGroup
    {
        $groupId = $this->resolvedActiveGroupId();

        if ($groupId === null) {
            return null;
        }

        /** @var Course $course */
        $course = $this->getOwnerRecord();

        return $course->userGroups()->where('lms_user_groups.id', $groupId)->first();
    }

    /**
     * @return array<string, array{rules: array<string, mixed>}>
     */
    protected function filterStateForActiveGroup(): array
    {
        $group = $this->activeGroup();
        $sources = $group?->rules['sources'] ?? [];
        $rulesBySource = [];

        foreach ($sources as $source) {
            if (! is_array($source) || blank($source['source'] ?? null)) {
                continue;
            }

            $rulesBySource[(string) $source['source']] = is_array($source['rules'] ?? null)
                ? $source['rules']
                : [];
        }

        $state = [];

        foreach (app(UserGroupCriteriaRegistry::class)->sources() as $key => $source) {
            $state[$key] = [
                'rules' => $rulesBySource[$key] ?? [],
            ];
        }

        return $state;
    }

    protected function loadActiveGroupIntoFilters(): void
    {
        $state = $this->filterStateForActiveGroup();

        $this->tableFilters = $state;
        $this->tableDeferredFilters = $state;

        if (! isset($this->table)) {
            return;
        }

        $this->getTableFiltersForm()->fill($state);
    }

    protected function refreshMatchingUsersTable(): void
    {
        $this->resetPage();
        $this->flushCachedTableRecords();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function saveActiveGroup(array $data): void
    {
        $rules = [
            'version' => 1,
            'sources' => $this->normalizeFilterSources(
                $this->getTable()->hasDeferredFilters()
                    ? ($this->tableDeferredFilters ?? $this->tableFilters ?? [])
                    : ($this->tableFilters ?? [])
            ),
        ];

        try {
            $rules = app(UserGroupRuleMatcher::class)->normalize($rules);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()
                ->title('Invalid criteria')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        if ($rules['sources'] === []) {
            Notification::make()
                ->title('Add at least one criteria rule before saving.')
                ->danger()
                ->send();

            return;
        }

        /** @var Course $course */
        $course = $this->getOwnerRecord();
        $group = $this->activeGroup();
        $makeDefault = $course->userGroups()->wherePivot('is_default', true)->doesntExist();

        if ($group === null) {
            $group = UserGroup::query()->create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rules' => $rules,
                'is_active' => true,
            ]);
            $course->userGroups()->attach($group->id, [
                'is_default' => $makeDefault,
            ]);
            $this->activeGroupId = (string) $group->id;

            if ($makeDefault) {
                $course->setDefaultUserGroup($group->id);
            }
        } else {
            $group->forceFill([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'rules' => $rules,
                'is_active' => true,
            ])->save();
        }

        app(UserGroupMembershipSynchronizer::class)->queueRebuild($group->fresh());
        $this->loadActiveGroupIntoFilters();

        Notification::make()
            ->title('User group saved')
            ->success()
            ->send();
    }

    protected function removeActiveGroup(): void
    {
        $group = $this->activeGroup();

        if ($group === null) {
            return;
        }

        /** @var Course $course */
        $course = $this->getOwnerRecord();
        $wasDefault = (bool) $group->pivot?->is_default;
        $course->userGroups()->detach($group->id);

        if ($group->courses()->count() === 0) {
            $group->delete();
        }

        $nextDefault = $course->defaultUserGroup();

        if ($wasDefault && $nextDefault !== null) {
            $course->setDefaultUserGroup($nextDefault->id);
        }

        $this->activeGroupId = $nextDefault !== null ? (string) $nextDefault->id : null;
        $this->loadActiveGroupIntoFilters();

        Notification::make()
            ->title('User group removed from course')
            ->success()
            ->send();
    }

    /**
     * @return list<array{source: string, rules: array<string, mixed>}>
     */
    protected function normalizeFilterSources(mixed $state): array
    {
        if (! is_array($state)) {
            return [];
        }

        $normalized = [];
        $configuredKeys = array_keys(app(UserGroupCriteriaRegistry::class)->sources());

        // Official QueryBuilder filters: tableFilters[sourceKey]['rules']
        foreach ($configuredKeys as $sourceKey) {
            $filterState = $state[$sourceKey] ?? null;

            if (! is_array($filterState)) {
                continue;
            }

            $rules = $filterState['rules'] ?? null;

            if (! is_array($rules)) {
                continue;
            }

            $normalized[] = [
                'source' => $sourceKey,
                'rules' => $rules,
            ];
        }

        if ($normalized !== []) {
            return $normalized;
        }

        // Legacy: single "criteria" filter with per-source RuleBuilder trees.
        if (isset($state['criteria']) && is_array($state['criteria'])) {
            foreach ($configuredKeys as $sourceKey) {
                if (! array_key_exists($sourceKey, $state['criteria']) || ! is_array($state['criteria'][$sourceKey])) {
                    continue;
                }

                $normalized[] = [
                    'source' => $sourceKey,
                    'rules' => $state['criteria'][$sourceKey],
                ];
            }

            if ($normalized !== []) {
                return $normalized;
            }

            $legacySources = $state['criteria']['sources'] ?? $state['criteria'];
        } else {
            $legacySources = $state['sources'] ?? $state;
        }

        if (! is_array($legacySources)) {
            return [];
        }

        foreach ($legacySources as $source) {
            if (! is_array($source)) {
                continue;
            }

            if (isset($source['type'])) {
                if (blank($source['type'])) {
                    continue;
                }

                $normalized[] = [
                    'source' => (string) $source['type'],
                    'rules' => is_array($source['data']['rules'] ?? null) ? $source['data']['rules'] : [],
                ];

                continue;
            }

            if (blank($source['source'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'source' => (string) $source['source'],
                'rules' => is_array($source['rules'] ?? null) ? $source['rules'] : [],
            ];
        }

        return $normalized;
    }

    public function updatedActiveGroupId(mixed $value): void
    {
        /** @var Course $course */
        $course = $this->getOwnerRecord();

        if (blank($value)) {
            $this->activeGroupId = null;
            $this->loadActiveGroupIntoFilters();
            $this->refreshMatchingUsersTable();

            return;
        }

        $groupId = (int) $value;
        $this->activeGroupId = (string) $groupId;

        if ($course->userGroups()->where('lms_user_groups.id', $groupId)->exists()) {
            $course->setDefaultUserGroup($groupId);
        }

        $this->loadActiveGroupIntoFilters();
        $this->refreshMatchingUsersTable();
    }
}
