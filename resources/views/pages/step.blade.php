<x-filament-panels::page>
    @if($step->text)
        <div class="text-gray-600 markdown-content dark:text-gray-400">
            {!! \Illuminate\Support\Str::markdown($step->text) !!}
        </div>
    @endif

    <style>
    .markdown-content {
        font-size: 0.875rem;
        line-height: 1.5;
    }

    .markdown-content h1 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 1rem;
        margin-top: 1.5rem;
    }

    .markdown-content h2 {
        font-size: 1.25rem;
        font-weight: 700;
        margin-bottom: 0.75rem;
        margin-top: 1.25rem;
    }

    .markdown-content h3 {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        margin-top: 1rem;
    }

    .markdown-content p {
        margin-bottom: 0.75rem;
    }

    .markdown-content ul, .markdown-content ol {
        margin-bottom: 0.75rem;
        margin-left: 1.5rem;
    }

    .markdown-content ul {
        list-style-type: disc;
    }

    .markdown-content ol {
        list-style-type: decimal;
    }

    .markdown-content li {
        margin-bottom: 0.25rem;
        display: list-item;
    }

    .markdown-content blockquote {
        border-left: 4px solid #d1d5db;
        padding-left: 1rem;
        font-style: italic;
        margin: 0.75rem 0;
    }

    .markdown-content code {
        background-color: #f3f4f6;
        padding: 0.125rem 0.25rem;
        border-radius: 0.25rem;
        font-size: 0.875rem;
    }

    .dark .markdown-content code {
        background-color: #374151;
    }

    .markdown-content pre {
        background-color: #f3f4f6;
        padding: 0.75rem;
        border-radius: 0.25rem;
        margin-bottom: 0.75rem;
        overflow-x: auto;
    }

    .dark .markdown-content pre {
        background-color: #374151;
    }

    .markdown-content pre code {
        background-color: transparent;
        padding: 0;
    }

    .markdown-content a {
        color: #2563eb;
        text-decoration: none;
    }

    .markdown-content a:hover {
        text-decoration: underline;
    }

    .dark .markdown-content a {
        color: #60a5fa;
    }

    .markdown-content strong {
        font-weight: 600;
    }

    .markdown-content em {
        font-style: italic;
    }
    </style>

    @if (is_null($step->material))
        <div class="flex items-center justify-center min-h-[60vh]">
            <x-filament::card class="py-12 w-full max-w-md">
                <div class="flex flex-col justify-center items-center text-center">
                    <div class="mb-4 text-lg font-semibold text-red-600">
                        The material for this step is missing or has been deleted.
                    </div>
                    <x-filament::button color="gray" size="md" class="w-auto next-button" wire:click="$dispatch('complete-step')">
                        Next
                    </x-filament::button>
                </div>
            </x-filament::card>
        </div>
    @elseif ($step->material_type == 'video')
        <livewire:video-step :step="$step"/>
    @elseif ($step->material_type == 'form')
        <livewire:form-step :step="$step"/>
    @elseif ($step->material_type == 'document')
        <livewire:document-step :step="$step"/>
    @elseif ($step->material_type == 'link')
        <livewire:link-step :step="$step"/>
    @elseif ($step->material_type == 'test')
        <livewire:test-step :step="$step"/>
    @elseif ($step->material_type == 'image')
        <livewire:image-step :step="$step"/>
    @else
        unsupported material type: {{ $step->material_type }}
    @endif
</x-filament-panels::page>
