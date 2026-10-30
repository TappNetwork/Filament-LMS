<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Exports;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Services\CourseProgressQueryService;

final class CourseEvaluationEntriesExport implements Export, WithMultipleSheets
{
    public function __construct(
        protected EloquentBuilder|QueryBuilder $query,
        protected bool $evaluationsOnly = true,
    ) {}

    public function sheets(): array
    {
        /** @var Collection<int, mixed> $reportRows */
        $reportRows = $this->query->get();
        $singleName = CourseProgressQueryService::reportUsesSingleNameColumn();

        /** @var array<string, true> $allowedPairs */
        $allowedPairs = [];
        /** @var array<int, array{first_name: string, last_name: string, email: string}> $people */
        $people = [];
        /** @var array<int, string> $courseNames */
        $courseNames = [];

        foreach ($reportRows as $row) {
            $userId = (int) $row->user_id;
            $courseId = (int) $row->course_id;
            $allowedPairs[$userId.'-'.$courseId] = true;
            $people[$userId] = [
                'first_name' => (string) $row->user_first_name,
                'last_name' => (string) $row->user_last_name,
                'email' => (string) $row->user_email,
            ];
            $courseNames[$courseId] = (string) $row->course_name;
        }

        if ($allowedPairs === []) {
            return [$this->emptySheet($singleName)];
        }

        $evaluationCourseIds = Course::query()
            ->whereNotNull('evaluation_course_id')
            ->pluck('evaluation_course_id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->all();

        $stepUsers = StepUser::query()
            ->whereNotNull('filament_form_user_id')
            ->whereIn('user_id', array_keys($people))
            ->with(['step.lesson'])
            ->get();

        $entries = FilamentFormUser::query()
            ->whereIn('id', $stepUsers->pluck('filament_form_user_id')->filter()->unique()->all())
            ->get()
            ->keyBy('id');

        /** @var array<int, list<array{first_name: string, last_name: string, email: string, course: string, submitted_at: string, answers: array<string, string>}>> $rowsByCourse */
        $rowsByCourse = [];

        foreach ($stepUsers as $stepUser) {
            $step = $stepUser->step;
            $lessonCourseId = $step instanceof Step ? $step->lesson->course_id : null;
            $primaryCourseId = $stepUser->evaluation_primary_course_id;
            $userId = $stepUser->user_id;

            $matchesLesson = $lessonCourseId !== null && isset($allowedPairs[$userId.'-'.(int) $lessonCourseId]);
            $matchesPrimary = $primaryCourseId !== null && isset($allowedPairs[$userId.'-'.(int) $primaryCourseId]);

            if (! $matchesLesson && ! $matchesPrimary) {
                continue;
            }

            $isEvaluation = $primaryCourseId !== null
                || ($lessonCourseId !== null && in_array((int) $lessonCourseId, $evaluationCourseIds, true));

            if ($isEvaluation !== $this->evaluationsOnly) {
                continue;
            }

            $entry = $entries->get($stepUser->filament_form_user_id);

            if (! $entry instanceof FilamentFormUser) {
                continue;
            }

            $sheetCourseId = $primaryCourseId ?? $lessonCourseId;

            if ($sheetCourseId === null) {
                continue;
            }
            $person = $people[$userId];
            $rowsByCourse[$sheetCourseId][] = [
                'first_name' => $person['first_name'],
                'last_name' => $person['last_name'],
                'email' => $person['email'],
                'course' => '',
                'submitted_at' => $this->submittedAt($entry),
                'answers' => $this->answers($entry),
            ];
        }

        if ($rowsByCourse === []) {
            return [$this->emptySheet($singleName)];
        }

        $missingCourseIds = array_diff(array_keys($rowsByCourse), array_keys($courseNames));

        if ($missingCourseIds !== []) {
            $loadedNames = Course::query()
                ->whereIn('id', $missingCourseIds)
                ->pluck('name', 'id');

            foreach ($loadedNames as $courseId => $name) {
                $courseNames[(int) $courseId] = (string) $name;
            }
        }

        uksort(
            $rowsByCourse,
            fn (int $left, int $right): int => strcasecmp(
                $courseNames[$left] ?? '',
                $courseNames[$right] ?? '',
            ),
        );

        $sheets = [];
        /** @var array<string, true> $usedTitles */
        $usedTitles = [];

        foreach ($rowsByCourse as $courseId => $rows) {
            $courseName = $courseNames[$courseId] ?? 'Course';

            foreach ($rows as $index => $row) {
                $rows[$index]['course'] = $courseName;
            }

            usort(
                $rows,
                fn (array $left, array $right): int => [$left['submitted_at'], $left['email']]
                    <=> [$right['submitted_at'], $right['email']],
            );

            $sheets[] = new CourseEvaluationEntriesSheet(
                $this->sheetTitle($courseName, $usedTitles),
                $rows,
                $singleName,
                $this->fieldHeadings($rows),
            );
        }

        return $sheets;
    }

    private function emptySheet(bool $singleName): CourseEvaluationEntriesSheet
    {
        $title = $this->evaluationsOnly ? 'Evaluation answers' : 'Course answers';

        return new CourseEvaluationEntriesSheet($title, [], $singleName, []);
    }

    /**
     * @return array<string, string>
     */
    private function answers(FilamentFormUser $entry): array
    {
        $answers = [];

        foreach ($entry->entry ?? [] as $fieldEntry) {
            if (! is_array($fieldEntry)) {
                continue;
            }

            $field = $fieldEntry['field'] ?? null;

            if (! is_string($field) || $field === '') {
                continue;
            }

            $answer = $fieldEntry['answer'] ?? '';
            $answers[$field] = is_array($answer) ? (json_encode($answer) ?: '') : (string) $answer;
        }

        return $answers;
    }

    private function submittedAt(FilamentFormUser $entry): string
    {
        $submittedAt = $entry->getAttribute('created_at');

        if ($submittedAt instanceof DateTimeInterface) {
            return $submittedAt->format('Y-m-d H:i:s');
        }

        return (string) $submittedAt;
    }

    /**
     * @param  list<array{answers: array<string, string>}>  $rows
     * @return list<string>
     */
    private function fieldHeadings(array $rows): array
    {
        $headings = [];

        foreach ($rows as $row) {
            foreach (array_keys($row['answers']) as $field) {
                if (! in_array($field, $headings, true)) {
                    $headings[] = $field;
                }
            }
        }

        return $headings;
    }

    /**
     * @param  array<string, true>  $usedTitles
     */
    private function sheetTitle(string $courseName, array &$usedTitles): string
    {
        $title = trim(str_replace(['\\', '/', '*', '?', ':', '[', ']'], ' ', $courseName));
        $title = $title === '' ? 'Course' : mb_substr($title, 0, 31);
        $base = $title;
        $index = 2;

        while (isset($usedTitles[mb_strtolower($title)])) {
            $suffix = ' ('.$index.')';
            $title = mb_substr($base, 0, 31 - mb_strlen($suffix)).$suffix;
            $index++;
        }

        $usedTitles[mb_strtolower($title)] = true;

        return $title;
    }
}
