<div>
    @if($entry)
        <div class="mb-8">
            @livewire('view-graded-entry', ['test' => $test, 'entry' => $entry])
        </div>
        
        @if($testGrade !== null && $testGrade < 100.0 && $step->require_perfect_score)
            <x-filament::section class="mt-6">
                <div class="space-y-4">
                    <div class="rounded-lg bg-danger-50 dark:bg-danger-900/20 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-filament::icon
                                    icon="heroicon-o-exclamation-triangle"
                                    class="h-5 w-5 text-danger-600 dark:text-danger-400"
                                />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-danger-800 dark:text-danger-200">
                                    Test Not Passed
                                </h3>
                                <div class="mt-2 text-sm text-danger-700 dark:text-danger-300">
                                    <p>
                                        You must answer all questions correctly to proceed. Please review the material and try again.
                                        @if($step->lesson->course->required_test_percentage !== null)
                                            <br><br>
                                            <strong>Note:</strong> This course requires an overall test average of at least {{ $step->lesson->course->required_test_percentage }}% to receive your certificate.
                                        @endif
                                    </p>
                                </div>
                                <div class="mt-4 flex gap-3">
                                    @if($step->retryStep)
                                        <x-filament::button
                                            tag="a"
                                            :href="$step->retryStep->url"
                                            color="danger"
                                            outlined
                                        >
                                            Review Material
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button
                                        wire:click="retakeTest"
                                        color="primary"
                                    >
                                        Retake Test
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        @elseif($testGrade !== null && $testGrade < 100.0)
            <x-filament::section class="mt-6">
                <div class="rounded-lg bg-gray-50 dark:bg-gray-800/50 p-4">
                    <p class="text-sm text-gray-700 dark:text-gray-300">
                        You didn't get a perfect score. You can continue or retake the test.
                        @if($step->lesson->course->required_test_percentage !== null)
                            <br><br>
                            <strong>Note:</strong> This course requires an overall test average of at least {{ $step->lesson->course->required_test_percentage }}% to receive your certificate. Retaking this test now may help you meet that requirement.
                        @endif
                    </p>
                    <div class="mt-3 flex gap-3">
                        @if($step->retryStep)
                            <x-filament::button
                                tag="a"
                                :href="$step->retryStep->url"
                                color="gray"
                                outlined
                            >
                                Review Material
                            </x-filament::button>
                        @endif
                        <x-filament::button
                            wire:click="retakeTest"
                            color="primary"
                        >
                            Retake Test
                        </x-filament::button>
                    </div>
                </div>
            </x-filament::section>
        @endif
    @else
        @livewire('create-test-entry', ['test' => $test])
    @endif

    <x-filament-lms::next-button :disabled="!$step->is_optional && (!$entry || ($step->require_perfect_score && !$testPassed))" />
</div> 
