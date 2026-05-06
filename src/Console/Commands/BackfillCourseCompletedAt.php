<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Models\Course;

class BackfillCourseCompletedAt extends Command
{
    protected $signature = 'filament-lms:backfill-course-completed-at
                            {--dry-run : Log and report only, do not update}
                            {--log-each : Log each course/user decision to console}';

    protected $description = 'Backfill lms_course_user: add/update rows with completed_at from step data for users who completed all steps';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $logEach = (bool) $this->option('log-each');

        if ($dryRun) {
            $this->info('DRY RUN - no changes will be written.');
        }

        $courseIds = DB::table('lms_courses')->pluck('id');
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

            $userIdsWhoCompletedAllSteps = DB::table('lms_step_user')
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

                $maxCompletedAt = DB::table('lms_step_user')
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

                    if ($exists) {
                        DB::table('lms_course_user')
                            ->where('course_id', $courseId)
                            ->where('user_id', $userId)
                            ->update(['completed_at' => $maxCompletedAt, 'updated_at' => now()]);
                    } else {
                        $now = now();
                        DB::table('lms_course_user')->insert([
                            'course_id' => $courseId,
                            'user_id' => $userId,
                            'completed_at' => $maxCompletedAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
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
}
