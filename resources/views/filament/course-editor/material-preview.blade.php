@php
    if (! $material instanceof \Illuminate\Database\Eloquent\Model) {
        return;
    }

    $name = $material->getAttributes()['name'] ?? 'Material';
    $url = \Tapp\FilamentLms\Support\CourseMaterialForm::urlFrom($material);
    $urlLabel = filled($url) ? \Tapp\FilamentLms\Support\CourseMaterialForm::urlPreviewLabel($url) : null;
@endphp

<div class="rounded-xl p-4 ring-1 ring-gray-950/10 dark:ring-white/10">
    @if ($materialType === 'video' && filled($url))
        <div class="mb-3 overflow-hidden rounded-lg bg-gray-950">
            <iframe
                src="{{ $url }}"
                title="{{ $name }}"
                class="aspect-video w-full"
                allow="fullscreen"
                loading="lazy"
            ></iframe>
        </div>
        <div class="text-sm font-medium">{{ $name }}</div>
        <div class="text-sm text-gray-500">
            @include('filament-lms::filament.course-editor.material-url', ['url' => $url, 'label' => $urlLabel])
        </div>
    @elseif ($materialType === 'link' && filled($url))
        <div class="text-sm font-medium">{{ $name }}</div>
        <div class="text-sm text-gray-500">
            @include('filament-lms::filament.course-editor.material-url', ['url' => $url, 'label' => $urlLabel])
        </div>
    @elseif ($materialType === 'document')
        <div class="text-sm font-medium">{{ $name }}</div>
        @if (method_exists($material, 'getFirstMedia') && ($media = $material->getFirstMedia()))
            <div class="text-sm text-gray-500">{{ $media->human_readable_size ?? '' }}</div>
        @endif
    @elseif ($materialType === 'image')
        <div class="text-sm font-medium">{{ $name }}</div>
    @else
        <div class="text-sm font-medium">{{ $name }}</div>
    @endif
</div>
