<div class="rounded-xl p-4 ring-1 ring-gray-950/10 dark:ring-white/10">
    <div class="mb-2 text-sm font-medium text-gray-500">
        {{ __('filament-lms::course-editor.material.preview') }}
    </div>

    @if ($materialType === 'video' && filled($embedUrl))
        <div class="mb-3 overflow-hidden rounded-lg bg-gray-950">
            <iframe
                src="{{ $embedUrl }}"
                title="{{ $name }}"
                class="aspect-video w-full"
                allow="fullscreen"
                loading="lazy"
            ></iframe>
        </div>
    @endif

    @if (filled($name))
        <div class="text-sm font-medium">{{ $name }}</div>
    @endif

    @if (filled($url))
        <div class="text-sm text-gray-500">
            @include('filament-lms::filament.course-editor.material-url', [
                'url' => $url,
                'label' => \Tapp\FilamentLms\Support\CourseMaterialForm::urlPreviewLabel($url),
            ])
        </div>
    @endif
</div>
