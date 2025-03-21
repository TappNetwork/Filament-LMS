  <div class="group relative flex flex-col overflow-hidden rounded-lg border border-gray-200 bg-white">
    <img src="{{ $course->image_url }}" class="aspect-[3/4] w-full bg-gray-200 object-cover group-hover:opacity-75 sm:aspect-auto sm:h-96">
    <div class="flex flex-1 flex-col space-y-2 p-4">
      <h3 class="text-sm font-medium text-gray-900">
        <a href="{{ $course->linkToCurrentStep() }}">
          <span aria-hidden="true" class="absolute inset-0"></span>
          {{ $course->name }}
        </a>
      </h3>
      <p class="text-sm text-gray-500">
       {{ $course->description }}
      </p>
    </div>
    <div class="overflow-hidden bg-gray-200">
      <div class="h-2 bg-primary-600" style="width: {{$course->completion_percentage}}%"></div>
    </div>
  </div>
