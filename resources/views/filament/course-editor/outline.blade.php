<div>
    <div class="mb-4">
        <h2 class="text-base font-semibold">{{ __('filament-lms::course-editor.outline.heading') }}</h2>
        <p class="text-sm text-gray-500">{{ __('filament-lms::course-editor.outline.description') }}</p>
    </div>

    @if ($lessons->isEmpty())
        <div class="fi-sc-empty-state mb-4">
            <x-filament::empty-state
                :heading="__('filament-lms::course-editor.outline.empty_heading')"
                :description="__('filament-lms::course-editor.outline.empty_description')"
                icon="heroicon-o-book-open"
            />
        </div>
    @else
        <ul
            class="flex flex-col gap-4"
            @if ($canUpdateCourse)
                x-sortable
                x-on:end.stop="$wire.reorderLessons($event.target.sortable.toArray())"
            @endif
        >
            @foreach ($lessons as $lesson)
                <li
                    wire:key="lesson-{{ $lesson->getKey() }}"
                    @if ($canUpdateCourse)
                        x-sortable-item="{{ $lesson->getKey() }}"
                    @endif
                    class="flex items-start gap-2"
                >
                    @if ($canUpdateCourse)
                        <button
                            type="button"
                            x-sortable-handle
                            class="fi-icon-btn mt-3"
                            aria-label="{{ __('filament-lms::course-editor.outline.reorder_lesson') }}"
                            x-tooltip="{
                                content: @js(__('filament-lms::course-editor.outline.reorder_lesson')),
                                theme: $store.theme,
                            }"
                        >
                            <x-filament::icon icon="heroicon-o-bars-3" class="h-5 w-5" />
                        </button>
                    @endif

                    <div class="min-w-0 grow">
                        <x-filament::section
                            :heading="__('filament-lms::course-editor.outline.lesson_heading', [
                                'number' => $loop->iteration,
                                'name' => $lesson->name,
                                'count' => $lesson->steps->count(),
                                'steps' => trans_choice('filament-lms::course-editor.outline.step_count', $lesson->steps->count()),
                            ])"
                            collapsible
                            persist-collapsed
                            :collapse-id="'filament-lms-lesson-'.$lesson->getKey()"
                        >
                            <x-slot name="afterHeader">
                                <div class="flex items-center gap-2">
                                    @if (\Tapp\FilamentLms\Resources\LessonResource::can('update', $lesson))
                                        <x-filament::icon-button
                                            icon="heroicon-o-pencil-square"
                                            color="gray"
                                            :label="__('filament-lms::course-editor.outline.edit_lesson')"
                                            :tooltip="__('filament-lms::course-editor.outline.edit_lesson')"
                                            wire:click="mountAction('editLesson', { lesson: {{ $lesson->getKey() }} })"
                                        />
                                    @endif
                                    @if ($canUpdateCourse && \Tapp\FilamentLms\Resources\LessonResource::can('create'))
                                        <x-filament::icon-button
                                            icon="heroicon-o-square-2-stack"
                                            color="gray"
                                            :label="__('filament-lms::course-editor.outline.clone_lesson')"
                                            :tooltip="__('filament-lms::course-editor.outline.clone_lesson')"
                                            wire:click="mountAction('cloneLesson', { lesson: {{ $lesson->getKey() }} })"
                                        />
                                    @endif
                                    @if (\Tapp\FilamentLms\Resources\LessonResource::can('delete', $lesson))
                                        <x-filament::icon-button
                                            icon="heroicon-o-trash"
                                            color="danger"
                                            :label="__('filament-lms::course-editor.outline.delete_lesson')"
                                            :tooltip="__('filament-lms::course-editor.outline.delete_lesson')"
                                            wire:click="mountAction('deleteLesson', { lesson: {{ $lesson->getKey() }} })"
                                        />
                                    @endif
                                </div>
                            </x-slot>

                            <ul
                                class="flex flex-col gap-2"
                                @if ($canUpdateCourse)
                                    x-sortable
                                    x-on:end.stop="$wire.reorderSteps({{ $lesson->getKey() }}, $event.target.sortable.toArray())"
                                @endif
                            >
                                @foreach ($lesson->steps as $step)
                                    @php
                                        $badge = \Tapp\FilamentLms\Support\MaterialBadge::class;
                                    @endphp
                                    <li
                                        wire:key="step-{{ $step->getKey() }}"
                                        @if ($canUpdateCourse)
                                            x-sortable-item="{{ $step->getKey() }}"
                                        @endif
                                        class="flex items-center gap-2 rounded-lg px-2 py-2 ring-1 ring-gray-950/10 dark:ring-white/10"
                                    >
                                        @if ($canUpdateCourse)
                                            <button
                                                type="button"
                                                x-sortable-handle
                                                class="fi-icon-btn"
                                                aria-label="{{ __('filament-lms::course-editor.outline.reorder_step') }}"
                                                x-tooltip="{
                                                    content: @js(__('filament-lms::course-editor.outline.reorder_step')),
                                                    theme: $store.theme,
                                                }"
                                            >
                                                <x-filament::icon icon="heroicon-o-bars-3" class="h-4 w-4" />
                                            </button>
                                        @endif

                                        <span class="min-w-0 grow truncate text-sm font-medium">
                                            {{ __('filament-lms::course-editor.outline.step_heading', ['number' => $loop->iteration, 'name' => $step->name]) }}
                                        </span>

                                        <x-filament::badge
                                            :color="$badge::color($step)"
                                            :icon="$badge::icon($step)"
                                            :style="$badge::isMissing($step) ? 'border-style: dashed' : null"
                                        >
                                            {{ $badge::label($step) }}
                                        </x-filament::badge>

                                        @if ($step->is_optional)
                                            <x-filament::badge color="gray">
                                                {{ __('filament-lms::course-editor.outline.optional') }}
                                            </x-filament::badge>
                                        @endif

                                        @if (\Tapp\FilamentLms\Resources\StepResource::can('update', $step))
                                            <x-filament::icon-button
                                                icon="heroicon-o-pencil-square"
                                                color="gray"
                                                :label="__('filament-lms::course-editor.outline.edit_step')"
                                                :tooltip="__('filament-lms::course-editor.outline.edit_step')"
                                                wire:click="mountAction('editStep', { step: {{ $step->getKey() }} })"
                                            />
                                        @endif
                                        @if (\Tapp\FilamentLms\Resources\StepResource::can('create') && \Tapp\FilamentLms\Resources\LessonResource::can('update', $lesson))
                                            <x-filament::icon-button
                                                icon="heroicon-o-square-2-stack"
                                                color="gray"
                                                :label="__('filament-lms::course-editor.outline.duplicate_step')"
                                                :tooltip="__('filament-lms::course-editor.outline.duplicate_step')"
                                                wire:click="duplicateStep({{ $step->getKey() }})"
                                            />
                                        @endif
                                        @if (\Tapp\FilamentLms\Resources\StepResource::can('delete', $step))
                                            <x-filament::icon-button
                                                icon="heroicon-o-trash"
                                                color="danger"
                                                :label="__('filament-lms::course-editor.outline.delete_step')"
                                                :tooltip="__('filament-lms::course-editor.outline.delete_step')"
                                                wire:click="mountAction('deleteStep', { step: {{ $step->getKey() }} })"
                                            />
                                        @endif
                                    </li>
                                @endforeach
                            </ul>

                            @if (\Tapp\FilamentLms\Resources\StepResource::can('create') && \Tapp\FilamentLms\Resources\LessonResource::can('update', $lesson))
                                <div class="mt-3">
                                    <x-filament::button
                                        color="gray"
                                        size="sm"
                                        icon="heroicon-o-plus"
                                        wire:click="mountAction('editStep', { lesson: {{ $lesson->getKey() }} })"
                                    >
                                        {{ __('filament-lms::course-editor.outline.add_step') }}
                                    </x-filament::button>
                                </div>
                            @endif
                        </x-filament::section>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @if ($canUpdateCourse && \Tapp\FilamentLms\Resources\LessonResource::can('create'))
        <div class="mt-4">
            <x-filament::button
                color="gray"
                outlined
                icon="heroicon-o-plus"
                style="width: 100%; border-style: dashed"
                wire:click="mountAction('createLesson')"
            >
                {{ __('filament-lms::course-editor.outline.add_lesson') }}
            </x-filament::button>
        </div>
    @endif

    @includeIf('filament-actions::components.modals')
</div>
