<div class="space-y-3">
    <p class="fi-ta-header-description text-sm text-gray-500 dark:text-gray-400">
        @if (count($groupOptions) > 0)
            Add criteria below (each source is combined with AND), click Apply filters to preview matching users, then Save as group to assign them to this course. Switch Default group to edit another saved group, or choose “New unsaved criteria” to create another.
        @else
            Add criteria below (each source is combined with AND), click Apply filters to preview matching users, then Save as group to assign them to this course.
        @endif
    </p>

    @if (count($groupOptions) > 0)
        <div class="fi-fo-field-wrp max-w-sm">
            <label for="course-default-user-group" class="fi-fo-field-wrp-label inline-flex items-center gap-x-1.5 text-sm font-medium text-gray-950 dark:text-white">
                Default group
            </label>
            <div class="mt-1">
                <x-filament::input.wrapper>
                    <x-filament::input.select
                        id="course-default-user-group"
                        wire:model.live="activeGroupId"
                    >
                        <option value="">New unsaved criteria</option>
                        @foreach ($groupOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    @endif
</div>
