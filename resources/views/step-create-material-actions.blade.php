<div class="p-4 bg-gray-50 dark:bg-gray-800 rounded-lg border">
    <div class="flex items-center justify-between">
        <div>
            <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                Don't see what you need?
            </h3>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Create a new {{ $get('material_type')::class === 'Tapp\FilamentLms\Models\Video' ? 'video' : ($get('material_type')::class === 'Tapp\FilamentLms\Models\Document' ? 'document' : ($get('material_type')::class === 'Tapp\FilamentLms\Models\Link' ? 'link' : 'image')) }} to use in this step.
            </p>
        </div>
        <div>
            @if($get('material_type') === 'Tapp\FilamentLms\Models\Video')
                <x-filament::button
                    wire:click="$dispatch('open-modal', { id: 'create-video-modal' })"
                    color="success"
                    size="sm"
                    icon="heroicon-o-video-camera"
                >
                    Create Video
                </x-filament::button>
            @elseif($get('material_type') === 'Tapp\FilamentLms\Models\Document')
                <x-filament::button
                    wire:click="$dispatch('open-modal', { id: 'create-document-modal' })"
                    color="success"
                    size="sm"
                    icon="heroicon-o-document"
                >
                    Create Document
                </x-filament::button>
            @elseif($get('material_type') === 'Tapp\FilamentLms\Models\Link')
                <x-filament::button
                    wire:click="$dispatch('open-modal', { id: 'create-link-modal' })"
                    color="success"
                    size="sm"
                    icon="heroicon-o-link"
                >
                    Create Link
                </x-filament::button>
            @elseif($get('material_type') === 'Tapp\FilamentLms\Models\Image')
                <x-filament::button
                    wire:click="$dispatch('open-modal', { id: 'create-image-modal' })"
                    color="success"
                    size="sm"
                    icon="heroicon-o-photo"
                >
                    Create Image
                </x-filament::button>
            @endif
        </div>
    </div>
</div>

