<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tapp\FilamentLms\Models\Course;

class BackfillCourseCompletedAt extends Command
{
    protected $signature = 'filament-lms:backfill-course-completed-at
                            {--dry-run : Log and report only, do not update}
                            {--log-each : Log each course/user decision to console}
                            {--course=* : Only backfill these course ids}
                            {--steps-existing-at-completion : Also count users who completed every step that existed when they finished, even if steps were added later. Never overwrites an existing completed_at.}';

    protected $description = 'Backfill lms_course_user: add/update rows with completed_at from step data for users who completed all steps';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $logEach = (bool) $this->option('log-each');

        if ($dryRun) {
            $this->info('DRY RUN - no changes will be written.');
        }

        $stepsExistingAtCompletion = (bool) $this->option('steps-existing-at-completion');
        $onlyCourseIds = array_filter((array) $this->option('course'), fn ($id) => $id !== null && $id !== '');
        $hasExplicitColumn = Schema::hasColumn('lms_course_user', 'is_explicitly_assigned');

        $courseIds = DB::table('lms_courses')
            ->when($onlyCourseIds !== [], fn ($query) => $query->whereIn('id', $onlyCourseIds))
            ->pluck('id');
        $totalInsertedOrUpdated = 0;
        $totalSkippedTestPct = 0;
        $totalSkippedNoMaxDate = 0;

        foreach ($courseIds as $courseId) {
            $stepIds = DB::table('lms_steps')
                ->join('lms_lessons', 'lms_lessons.id', '=', 'lms_steps.lesson_id')
                ->where('lms_lessons.course_id', $courseId)
                ->pluck('lms_steps.id');

            if ($stepIds->isEmpty()) {
                if ($logEach) {
                    $this->line("Course {$courseId}: no steps, skip.");
                }

                continue;
            }

            $totalSteps = $stepIds->count();

            $finishedAtByUser = $stepsExistingAtCompletion
                ? $this->finishedAtForUsersWhoCompletedStepsExistingAtCompletion((int) $courseId, $stepIds)
                : collect();

            $userIdsWhoCompletedAllSteps = $stepsExistingAtCompletion
                ? $finishedAtByUser->keys()
                : DB::table('lms_step_user')
                    ->whereIn('step_id', $stepIds)
                    ->whereNotNull('completed_at')
                    ->groupBy('user_id')
                    ->havingRaw('COUNT(DISTINCT step_id) = ?', [$totalSteps])
                    ->pluck('user_id');

            if ($userIdsWhoCompletedAllSteps->isEmpty()) {
                if ($logEach) {
                    $this->line("Course {$courseId}: no users with all steps completed.");
                }

                continue;
            }

            /** @phpstan-ignore function.alreadyNarrowedType */
            $course = method_exists(Course::class, 'withoutTenantScope')
                ? Course::withoutTenantScope()->find($courseId)
                : Course::find($courseId);

            if (! $course) {
                continue;
            }

            foreach ($userIdsWhoCompletedAllSteps as $userId) {
                if ($course->required_test_percentage !== null) {
                    $testSteps = $course->getOrderedTestSteps();
                    if ($testSteps->isNotEmpty()) {
                        $overall = $course->getOverallTestPercentageForUser($userId);
                        if ($overall < (float) $course->required_test_percentage) {
                            $totalSkippedTestPct++;
                            if ($logEach) {
                                $this->line("Course {$courseId} user {$userId}: test % {$overall} < required, skip.");
                            }

                            continue;
                        }
                    }
                }

                $maxCompletedAt = $finishedAtByUser->get($userId) ?? DB::table('lms_step_user')
                    ->whereIn('step_id', $stepIds)
                    ->where('user_id', $userId)
                    ->whereNotNull('completed_at')
                    ->max('completed_at');

                if (! $maxCompletedAt) {
                    $totalSkippedNoMaxDate++;
                    if ($logEach) {
                        $this->line("Course {$courseId} user {$userId}: no max completed_at, skip.");
                    }

                    continue;
                }

                if (! $dryRun) {
                    $exists = DB::table('lms_course_user')
                        ->where('course_id', $courseId)
                        ->where('user_id', $userId)
                        ->exists();

                    if ($exists && $stepsExistingAtCompletion) {
                        // Only fill missing completion; never move an existing completed_at.
                        DB::table('lms_course_user')
                            ->where('course_id', $courseId)
                            ->where('user_id', $userId)
                            ->whereNull('completed_at')
                            ->update(['completed_at' => $maxCompletedAt, 'updated_at' => now()]);
                    } elseif ($exists) {
                        DB::table('lms_course_user')
                            ->where('course_id', $courseId)
                            ->where('user_id', $userId)
                            ->update(['completed_at' => $maxCompletedAt, 'updated_at' => now()]);
                    } else {
                        $now = now();
                        DB::table('lms_course_user')->insert(array_merge([
                            'course_id' => $courseId,
                            'user_id' => $userId,
                            'completed_at' => $maxCompletedAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ], $hasExplicitColumn && $stepsExistingAtCompletion ? ['is_explicitly_assigned' => false] : []));
                    }
                }

                $totalInsertedOrUpdated++;
                if ($logEach) {
                    $this->line("Course {$courseId} user {$userId}: set completed_at = {$maxCompletedAt}");
                }
            }
        }

        $summary = [
            'inserted_or_updated' => $totalInsertedOrUpdated,
            'skipped_test_pct' => $totalSkippedTestPct,
            'skipped_no_max_date' => $totalSkippedNoMaxDate,
        ];
        $this->info('Backfill summary: '.json_encode($summary, JSON_PRETTY_PRINT));

        return Command::SUCCESS;
    }

    /**
     * For users with no completed_at on the pivot yet: the earliest step completion at which they
     * had completed every step of the course that existed at that moment. Steps created later,
     * and completions of them, do not affect the result.
     *
     * @param  Collection<int, mixed>  $stepIds
     * @return Collection<int|string, non-falsy-string>
     */
    private function finishedAtForUsersWhoCompletedStepsExistingAtCompletion(int $courseId, Collection $stepIds): Collection
    {
        $stepCreatedAt = DB::table('lms_steps')
            ->whereIn('id', $stepIds)
            ->whereNotNull('created_at')
            ->pluck('created_at', 'id')
            ->mapWithKeys(fn ($createdAt, $id) => [(int) $id => (string) $createdAt]);

        $alreadyCompleted = DB::table('lms_course_user')
            ->where('course_id', $courseId)
            ->whereNotNull('completed_at')
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return DB::table('lms_step_user')
            ->whereIn('step_id', $stepIds)
            ->whereNotNull('completed_at')
            ->get(['user_id', 'step_id', 'completed_at'])
            ->groupBy('user_id')
            ->reject(fn (Collection $rows, $userId): bool => in_array((string) $userId, $alreadyCompleted, true))
            ->map(function (Collection $rows) use ($stepCreatedAt): ?string {
                $done = [];

                foreach ($rows->sortBy('completed_at') as $row) {
                    $done[(int) $row->step_id] = true;
                    $at = (string) $row->completed_at;
                    $required = $stepCreatedAt->filter(fn (string $createdAt) => $createdAt <= $at)->keys();

                    if ($required->isNotEmpty() && $required->every(fn (int $id) => isset($done[$id]))) {
                        return $at;
                    }
                }

                return null;
            })
            ->filter();
    }
}
