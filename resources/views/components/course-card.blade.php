@php
    $creditsEnabled = config('filament-lms.credits_enabled');
    $creditCategories = $creditsEnabled ? $course->courseCreditCategories : collect();
    $isCompleted = $course->completed_at !== null;
    $isInProgress = ! $isCompleted && $course->completion_percentage > 0;
    $moduleCount = (int) ($course->lessons_count ?? $course->lessons()->count());
@endphp
<div class="flex overflow-hidden relative flex-col bg-white rounded-lg border border-gray-200 group">
  {{-- Image section: fixed height, image or grey + icon --}}
  <div class="relative h-28 shrink-0 overflow-hidden bg-gray-200">
    <img
      src="{{ $course->image_url }}"
      alt=""
      class="object-cover w-full h-full group-hover:opacity-90"
      onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
    />
    <div style="display:none" class="items-center justify-center w-full h-full bg-gray-300" aria-hidden="true">
      <x-heroicon-o-academic-cap class="size-12 shrink-0 text-gray-400" />
    </div>

    {{-- Credits overlay: top-right, one badge per category --}}
    @if ($creditsEnabled && $creditCategories->isNotEmpty())
      <div class="absolute top-2 right-2 flex flex-col items-end gap-1.5">
        @foreach ($creditCategories as $ccc)
          @if ($ccc->creditCategory)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-xs font-medium text-gray-800 shadow-sm">
              <span>{{ number_format((float) $ccc->credits, 1) }} {{ $ccc->creditCategory->name }}</span>
            </span>
          @endif
        @endforeach
      </div>
    @endif
  </div>

  <div class="flex min-h-0 flex-1 flex-col p-4">
    <div class="min-h-0 flex-1 space-y-2 pb-3">
      <h3 class="text-sm font-medium text-gray-900">
        <a href="{{ $course->linkToCurrentStep() }}">
          <span aria-hidden="true" class="absolute inset-0"></span>
          {{ $course->name }}
        </a>
      </h3>
      <p class="line-clamp-3 text-sm text-gray-500">
        {{ $course->description }}
      </p>
    </div>
    {{-- Module count and status pinned to bottom, just above progress bar --}}
    <div class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
      <p class="text-sm text-gray-500">
        {{ $moduleCount }} {{ Str::plural('module', $moduleCount) }}
      </p>
      @if ($isCompleted)
        <span class="inline-flex items-center rounded-full bg-success-50 px-2.5 py-1 text-xs font-medium text-success-700">Completed</span>
      @elseif ($isInProgress)
        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">In Progress</span>
      @else
        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-1 text-xs font-medium text-gray-600">Not Started</span>
      @endif
    </div>
  </div>

  {{-- Progress bar: fill color matches status badge (success / amber / gray) --}}
  @php
    $progressBarFillClass = $isCompleted ? 'bg-success-600' : ($isInProgress ? 'bg-amber-600' : 'bg-gray-400');
  @endphp
  <div class="shrink-0 overflow-hidden rounded-b-lg bg-gray-300">
    <div
      class="h-2 rounded-full transition-[width] {{ $progressBarFillClass }}"
      style="width: {{ min(100, (float) $course->completion_percentage) }}%"
    ></div>
  </div>
</div>
