@php
    use Illuminate\Support\Facades\Auth;
    use Tapp\FilamentLms\Services\CourseEvaluationService;
    use Tapp\FilamentLms\Services\ScormProgressService;

    $creditsEnabled = config('filament-lms.credits_enabled');
    $creditCategories = $creditsEnabled ? $course->courseCreditCategories : collect();
    $isCompleted = $course->completed_at !== null;
    $completionPercentage = $course->completion_percentage;
    $hasStarted = Auth::check()
        && app(ScormProgressService::class)->courseStartedByUser($course, Auth::user());
    $isInProgress = ! $isCompleted && ($completionPercentage > 0 || $hasStarted);
    $moduleCount = (int) ($course->lessons_count ?? $course->lessons()->count());
    $evaluationService = app(CourseEvaluationService::class);
    $pendingEvaluation = Auth::check()
        && $evaluationService->hasPendingEvaluationForUser($course, Auth::id());
    $evaluationUrl = $pendingEvaluation ? $course->evaluationSubmissionUrl() : null;
@endphp
<div class="flex overflow-hidden relative flex-col bg-white rounded-lg border border-gray-200 group">
  {{-- Image section: fixed height, image or grey + icon --}}
  <div class="relative h-28 shrink-0 overflow-hidden bg-gray-100">
    {{-- Fallback icon: always rendered as base layer --}}
    <div class="flex items-center justify-center w-full h-full" aria-hidden="true">
      <x-heroicon-o-academic-cap class="size-12 shrink-0 text-gray-300" />
    </div>

    {{-- Image: layered on top, covers icon when loaded successfully --}}
    @if ($course->image_url)
      <img
        src="{{ $course->image_url }}"
        alt=""
        class="absolute inset-0 object-cover w-full h-full group-hover:opacity-90"
        onerror="this.style.visibility='hidden';"
      />
    @endif

    {{-- Credits overlay: top-right, one badge per category --}}
    @if ($creditsEnabled && $creditCategories->isNotEmpty())
      <div class="absolute top-2 right-2 flex flex-col items-end gap-1.5">
        @foreach ($creditCategories as $ccc)
          @if ($ccc->creditCategory)
            <x-filament-lms::credit-badge :category="$ccc->creditCategory" class="gap-1.5 px-2.5 py-1 shadow-sm">
              {{ number_format((float) $ccc->credits, 1) }} {{ $ccc->creditCategory->name }}
            </x-filament-lms::credit-badge>
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
      @if ($pendingEvaluation && $evaluationUrl)
        <a
          href="{{ $evaluationUrl }}"
          class="relative z-10 inline-flex items-center rounded-lg bg-primary-600 px-2.5 py-1 text-xs font-semibold text-white shadow-sm ring-1 ring-primary-600 transition hover:bg-primary-700"
        >
          Complete Evaluation
        </a>
      @elseif ($isCompleted)
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
      style="width: {{ min(100, (float) $completionPercentage) }}%"
    ></div>
  </div>
</div>
