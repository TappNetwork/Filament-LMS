<div>
  <!-- Static sidebar for desktop -->
  <!-- lg:top-65 not working. use custom class -->
  <!-- TODO where is the best place for x-cloak? -->
  <!-- <div x-cloak class="desktop-sidebar hidden lg:fixed lg:bottom-0 lg:top-65 lg:z-50 lg:flex lg:w-72 lg:flex-col"> -->
  <div x-cloak class="desktop-sidebar fixed bottom-0 top-65 z-50 flex w-72 flex-col">
    <!-- Sidebar component, swap this element with another sidebar if you like -->
    <div class="flex grow flex-col gap-y-5 overflow-y-auto border-r border-gray-200 bg-white px-6">
      <div class="flex h-16 shrink-0 items-center justify-center">
        <span class="block rounded-md py-2 text-md/6 font-semibold text-gray-700">
            {{ $course->name }}
        </span>
      </div>
      <nav class="flex flex-1 flex-col">
        <ul role="list" class="flex flex-1 flex-col gap-y-7">
            @foreach ($course->lessons as $lesson)
            <li x-data="{expanded: {{$lesson->isActive() ? 1 : 0}}, active: {{$lesson->isActive() ? 1 : 0}}, toggle() {this.expanded = !this.expanded}}">
            <div>
              <button @click="toggle()" type="button" :class="{'text-primary-600 bg-gray-50': !expanded && active}"
                            class="flex w-full items-center gap-x-3 rounded-md p-2 text-left text-sm/6 font-semibold hover:bg-gray-50"
                            aria-controls="sub-menu-1" aria-expanded="false">
                <!-- Expanded: "rotate-90 text-gray-500", Collapsed: "text-gray-400" -->
                <svg class="h-5 w-5 shrink-0" :class="{'rotate-90 text-gray-500': expanded, 'text-gray-400': !expanded}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" data-slot="icon">
                  <path fill-rule="evenodd" d="M8.22 5.22a.75.75 0 0 1 1.06 0l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L11.94 10 8.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                </svg>
                {{ $lesson->name }}
                @if ($lesson->completed_at)
                <svg x-show="!expanded" class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                @endif
              </button>
              <!-- Expandable link section, show/hide based on state. -->
              <ul :class="{'hidden': !expanded}" class="{{$lesson->isActive() ? '' : 'hidden' }} mt-1 px-2" id="sub-menu-1">
                  @foreach ($lesson->steps as $step)
                <li>
                    <a href="{{ $step->url }}"
                       @class([
                           'flex w-full items-center gap-x-3 rounded-md py-2 pl-9 pr-2 text-sm/6 hover:bg-gray-50',
                           'bg-gray-50 text-primary-600' => $step->isActive(),
                           'text-gray-700' => ! $step->isActive(),
                         ])>
                      {{ $step->name }}
                        @if ($step->completed_at)
                            <svg class="h-5 w-5 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        @endif
                  </a>
                </li>
                @endforeach
              </ul>
            </div>
            </li>
            @endforeach
            <li class="-mx-6 mt-auto px-2 py-5">
                <div class="overflow-hidden rounded-full bg-gray-200 my-2">
                    <div class="h-2 rounded-full bg-primary-600" style="width: {{$course->completion_percentage}}%"></div>
                </div>
                <span class="text-center block rounded-md py-2 text-md/6 font-semibold text-gray-700">
                    @if($course->completed_at)
                        Course Completed!
                    @else
                        Course Progress
                    @endif
                </span>
                @if($course->completed_at)
                    <div class="text-center">
                        <a href="{{$course->certificateUrl()}}" type="button" class="inline-flex items-center gap-x-1.5 rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
                                <path fill-rule="evenodd" d="M9 1.5H5.625c-1.036 0-1.875.84-1.875 1.875v17.25c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V12.75A3.75 3.75 0 0 0 16.5 9h-1.875a1.875 1.875 0 0 1-1.875-1.875V5.25A3.75 3.75 0 0 0 9 1.5Zm6.61 10.936a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 14.47a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z" clip-rule="evenodd" />
                                <path d="M12.971 1.816A5.23 5.23 0 0 1 14.25 5.25v1.875c0 .207.168.375.375.375H16.5a5.23 5.23 0 0 1 3.434 1.279 9.768 9.768 0 0 0-6.963-6.963Z" />
                            </svg>
                            Certificate of Completion
                        </a>
                    </div>
                @endif
            </li>
        </ul>
      </nav>
    </div>
  </div>

  <main class="py-10 lg:pl-72">
    <div class="px-4 sm:px-6 lg:px-8">
        {{ $slot }}
    </div>
  </main>
</div>
