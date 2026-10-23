<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Filament\Facades\Filament;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Exports\CourseEvaluationEntriesExport;
use Tapp\FilamentLms\Exports\CourseEvaluationEntriesSheet;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CourseProgressReportRow;
use Tapp\FilamentLms\Models\Document;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\StepUser;
use Tapp\FilamentLms\Services\CourseProgressQueryService;
use Tapp\FilamentLms\Tests\TestUser;

beforeEach(function (): void {
    config(['filament-lms.user_model' => TestUser::class]);
    config(['filament-lms.tenancy.enabled' => false]);
    Filament::setTenant(null);
});

test('splits filtered form entries onto one sheet per training course', function (): void {
    $user = evaluationReportUser('Michelle', 'Weeks', 'michelle@example.com');

    $safeArms = evaluationCourse('Safe Arms for Babies', 'safe-arms');
    $document = Document::query()->create(['name' => 'Doc', 'file_path' => '/tmp/doc.pdf']);
    $safeArmsLesson = Lesson::factory()->create(['course_id' => $safeArms->id, 'order' => 1]);
    $safeArmsStep = Step::factory()->create([
        'lesson_id' => $safeArmsLesson->id,
        'order' => 1,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    $safeArmsStep->complete($user);

    $form = FilamentForm::query()->create(['name' => 'Course Evaluation Feedback']);
    $evaluationCourse = evaluationCourse('Course Evaluation', 'course-evaluation');
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id, 'order' => 1]);
    $evaluationStep = Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    $evaluationEntry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Course was clear', 'type' => 'Radio', 'answer' => 'Agree'],
            ['field' => 'Suggestions', 'type' => 'Checkbox', 'answer' => ['Add examples']],
        ],
    ]);
    $evaluationStep->complete($user, $safeArms->id);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $evaluationStep->id)
        ->update(['filament_form_user_id' => $evaluationEntry->id]);

    $decan = evaluationCourse('DECAN Training', 'decan');
    $decanLesson = Lesson::factory()->create(['course_id' => $decan->id, 'order' => 1]);
    $decanFormStep = Step::factory()->create([
        'lesson_id' => $decanLesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    Step::factory()->create([
        'lesson_id' => $decanLesson->id,
        'order' => 2,
        'material_type' => 'document',
        'material_id' => $document->id,
    ]);
    $decanEntry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Course was clear', 'type' => 'Radio', 'answer' => 'Neutral'],
        ],
    ]);
    $decanFormStep->complete($user);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $decanFormStep->id)
        ->update(['filament_form_user_id' => $decanEntry->id]);

    $completed = CourseProgressReportRow::query()
        ->fromSub(CourseProgressQueryService::buildQuery(), 'report')
        ->where('status', 'Completed');

    $sheets = (new CourseEvaluationEntriesExport($completed))->sheets();

    expect($sheets)->toHaveCount(1);

    $sheet = $sheets[0];
    expect($sheet)->toBeInstanceOf(CourseEvaluationEntriesSheet::class);
    expect($sheet->title())->toBe('Safe Arms for Babies');
    expect($sheet->headings())->toBe([
        'First Name',
        'Last Name',
        'Email',
        'Course',
        'Submitted At',
        'Course was clear',
        'Suggestions',
    ]);
    expect($sheet->array())->toBe([
        [
            'Michelle',
            'Weeks',
            'michelle@example.com',
            'Safe Arms for Babies',
            $evaluationEntry->created_at?->format('Y-m-d H:i:s'),
            'Agree',
            json_encode(['Add examples']),
        ],
    ]);
});

test('keeps an evaluation on the primary course sheet when the report is filtered to the evaluation course', function (): void {
    $user = evaluationReportUser('Sarah', 'Cole', 'sarah@example.com');
    $primary = evaluationCourse('DECAN Training', 'decan-training');
    $form = FilamentForm::query()->create(['name' => 'Course Evaluation Feedback']);
    $evaluationCourse = evaluationCourse('Course Evaluation', 'shared-evaluation');
    $lesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id, 'order' => 1]);
    $step = Step::factory()->create([
        'lesson_id' => $lesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    $entry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Course was clear', 'type' => 'Radio', 'answer' => 'Agree'],
        ],
    ]);
    $step->complete($user, $primary->id);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $step->id)
        ->update(['filament_form_user_id' => $entry->id]);

    $filteredToEvaluationCourse = CourseProgressReportRow::query()
        ->fromSub(CourseProgressQueryService::buildQuery(), 'report')
        ->where('course_id', $evaluationCourse->id);

    $sheets = (new CourseEvaluationEntriesExport($filteredToEvaluationCourse))->sheets();

    expect($sheets)->toHaveCount(1);
    expect($sheets[0]->title())->toBe('DECAN Training');
    expect($sheets[0]->array()[0][3])->toBe('DECAN Training');
});

test('exports every form entry by course while evaluations stay limited to evaluation submissions', function (): void {
    $user = evaluationReportUser('Michelle', 'Weeks', 'michelle@example.com');
    $form = FilamentForm::query()->create(['name' => 'Course Forms']);

    $safeArms = evaluationCourse('Safe Arms for Babies', 'safe-arms-forms');
    $safeArmsLesson = Lesson::factory()->create(['course_id' => $safeArms->id, 'order' => 1]);
    $courseFormStep = Step::factory()->create([
        'lesson_id' => $safeArmsLesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    $courseFormEntry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Course was clear', 'type' => 'Radio', 'answer' => 'Yes'],
        ],
    ]);
    $courseFormEntry->forceFill([
        'created_at' => '2026-01-01 10:00:00',
        'updated_at' => '2026-01-01 10:00:00',
    ])->save();
    $courseFormStep->complete($user);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $courseFormStep->id)
        ->update(['filament_form_user_id' => $courseFormEntry->id]);

    $evaluationCourse = evaluationCourse('Course Evaluation', 'safe-arms-evaluation');
    $evaluationLesson = Lesson::factory()->create(['course_id' => $evaluationCourse->id, 'order' => 1]);
    $evaluationStep = Step::factory()->create([
        'lesson_id' => $evaluationLesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    $evaluationEntry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Course was clear', 'type' => 'Radio', 'answer' => 'Agree'],
        ],
    ]);
    $evaluationEntry->forceFill([
        'created_at' => '2026-01-02 10:00:00',
        'updated_at' => '2026-01-02 10:00:00',
    ])->save();
    $evaluationStep->complete($user, $safeArms->id);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $evaluationStep->id)
        ->update(['filament_form_user_id' => $evaluationEntry->id]);

    $nutrition = evaluationCourse('Nutrition', 'nutrition');
    $nutritionLesson = Lesson::factory()->create(['course_id' => $nutrition->id, 'order' => 1]);
    $nutritionStep = Step::factory()->create([
        'lesson_id' => $nutritionLesson->id,
        'order' => 1,
        'material_type' => 'form',
        'material_id' => $form->id,
    ]);
    $nutritionEntry = FilamentFormUser::query()->create([
        'filament_form_id' => $form->id,
        'user_id' => $user->id,
        'entry' => [
            ['field' => 'Ready to start', 'type' => 'Radio', 'answer' => 'No'],
        ],
    ]);
    $nutritionStep->complete($user);
    StepUser::query()
        ->where('user_id', $user->id)
        ->where('step_id', $nutritionStep->id)
        ->update(['filament_form_user_id' => $nutritionEntry->id]);

    $report = CourseProgressReportRow::query()
        ->fromSub(CourseProgressQueryService::buildQuery(), 'report');

    $evaluationSheets = (new CourseEvaluationEntriesExport($report))->sheets();
    $formSheets = (new CourseEvaluationEntriesExport(clone $report, evaluationsOnly: false))->sheets();

    expect($evaluationSheets)->toHaveCount(1);
    expect($evaluationSheets[0]->title())->toBe('Safe Arms for Babies');
    expect($evaluationSheets[0]->array())->toHaveCount(1);
    expect($evaluationSheets[0]->array()[0][5])->toBe('Agree');

    expect(array_map(fn (CourseEvaluationEntriesSheet $sheet): string => $sheet->title(), $formSheets))
        ->toBe(['Nutrition', 'Safe Arms for Babies']);
    expect($formSheets[1]->array())->toHaveCount(2);
    expect($formSheets[1]->array()[0][5])->toBe('Yes');
    expect($formSheets[1]->array()[1][5])->toBe('Agree');
});

test('returns an empty evaluations sheet when the filtered report has no rows', function (): void {
    $sheets = (new CourseEvaluationEntriesExport(
        CourseProgressReportRow::query()
            ->fromSub(CourseProgressQueryService::buildQuery(), 'report')
            ->where('user_id', 0),
    ))->sheets();

    expect($sheets)->toHaveCount(1);
    expect($sheets[0]->title())->toBe('Evaluation answers');
    expect($sheets[0]->headings())->toBe([
        'First Name',
        'Last Name',
        'Email',
        'Course',
        'Submitted At',
    ]);
    expect($sheets[0]->array())->toBe([]);
});

function evaluationReportUser(string $firstName, string $lastName, string $email): TestUser
{
    $user = new TestUser;
    $user->forceFill([
        'name' => $firstName.' '.$lastName,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'password' => bcrypt('password'),
    ])->save();

    return $user;
}

function evaluationCourse(string $name, string $slug): Course
{
    return Course::factory()->create([
        'name' => $name,
        'slug' => $slug,
        'external_id' => $slug,
    ]);
}
