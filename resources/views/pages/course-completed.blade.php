<x-filament::section class="m-8">
    @if($qualifiedForCertificate)
        <x-slot name="heading">
            Congratulations! You have completed "{{ $course->name }}"
        </x-slot>

        <x-slot name="headerEnd">
        </x-slot>

        Download your certificate below.

        <div class="mt-4 flex gap-3">
            <a href="{{ \Tapp\FilamentLms\Pages\Dashboard::getUrl() }}">
                <x-filament::button color="gray">
                    View All Courses
                </x-filament::button>
            </a>
            <x-filament::button tag="a" href="{{ route('filament-lms::certificates.download', [$course->id]) }}">
                Download Certificate
            </x-filament::button>
        </div>
    @else
        <x-slot name="heading">
            You have completed all steps in "{{ $course->name }}"
        </x-slot>

        <x-slot name="headerEnd">
        </x-slot>

        <p class="text-gray-700 dark:text-gray-300 mb-4">
            Your combined test score is {{ number_format($overallPercent ?? 0, 1) }}%. You need at least {{ $requiredPercent ?? 0 }}% to receive the certificate.
        </p>

        @if(!empty($testStepDetails))
            <div class="mt-4 fi-infolist-ctn divide-y divide-gray-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:divide-white/5 dark:bg-gray-900 dark:ring-white/10">
                <dl class="divide-y divide-gray-200 dark:divide-white/5">
                    @foreach($testStepDetails as $detail)
                        <div class="fi-infolist-record px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-6 text-gray-950 dark:text-white">
                                <a href="{{ $detail['url'] }}" class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300">
                                    {{ $detail['name'] }}
                                </a>
                            </dt>
                            <dd class="mt-1 flex items-center justify-between gap-x-4 text-sm leading-6 text-gray-500 dark:text-gray-400 sm:col-span-2 sm:mt-0">
                                <div class="flex items-center gap-x-4">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ number_format($detail['percentage'], 1) }}%</span>
                                </div>
                                <div>
                                    @if($detail['percentage'] < 100)
                                        <a href="{{ $detail['url'] }}" class="fi-btn fi-btn-size-sm fi-btn-color-primary inline-flex items-center justify-center gap-x-1.5 rounded-lg px-2.5 py-1.5 text-sm font-semibold shadow-sm ring-1 transition duration-75 fi-color-primary bg-primary-600 text-white ring-primary-600 hover:bg-primary-700 dark:bg-primary-600 dark:text-white dark:ring-primary-500 dark:hover:bg-primary-700">
                                            Retake
                                        </a>
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">—</span>
                                    @endif
                                </div>
                            </dd>
                        </div>
                    @endforeach
                    <div class="fi-infolist-record px-4 py-6 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 bg-gray-50 dark:bg-white/5">
                        <dt class="text-sm font-semibold leading-6 text-gray-950 dark:text-white">
                            Total
                        </dt>
                        <dd class="mt-1 flex items-center justify-between gap-x-4 text-sm leading-6 text-gray-500 dark:text-gray-400 sm:col-span-2 sm:mt-0">
                            <div class="flex items-center gap-x-4">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ number_format($overallPercent ?? 0, 1) }}%</span>
                            </div>
                            <div>
                                <span class="text-gray-400 dark:text-gray-500">—</span>
                            </div>
                        </dd>
                    </div>
                </dl>
            </div>
        @endif
        <div class="mt-4">
            <a href="{{ \Tapp\FilamentLms\Pages\Dashboard::getUrl() }}">
                <x-filament::button color="gray">
                    View All Courses
                </x-filament::button>
            </a>
        </div>
    @endif
</x-filament::section>
