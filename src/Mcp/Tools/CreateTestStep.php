<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use RuntimeException;
use Tapp\FilamentFormBuilder\Enums\FilamentFieldTypeEnum;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Mcp\LmsTool;
use Tapp\FilamentLms\Models\Lesson;
use Tapp\FilamentLms\Models\Step;
use Tapp\FilamentLms\Models\Test;

class CreateTestStep extends LmsTool
{
    protected string $name = 'create_test_step';

    protected string $description = 'Create a graded quiz step: a locked form, its answer key (rubric), the test, and the step, in one transaction. To edit a quiz later, use the Filament admin.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('Lesson ID.')->required(),
            'name' => $schema->string()->description('Step name.')->required(),
            'test_name' => $schema->string()->description('Test and form name shown to learners.')->required(),
            'questions' => $schema->array()->description('Questions in display order.')->items($schema->object([
                'type' => $schema->string()->enum(array_keys(self::QUESTION_TYPES))->description('radio, select, or select_multiple need options. checkbox and toggle are true/false. text is graded by exact, case-sensitive match.')->required(),
                'text' => $schema->string()->description('Question text. Plain text; "<" is not allowed.')->required(),
                'options' => $schema->array()->items($schema->string())->description('Answer options for radio, select, and select_multiple. select_multiple options cannot contain commas.'),
                'correct_answer' => $schema->string()->description('Correct option for radio/select, "true" or "false" for checkbox/toggle, exact text for text.'),
                'correct_answers' => $schema->array()->items($schema->string())->description('Every correct option for select_multiple.'),
            ]))->required(),
            'text' => $schema->string()->description('Optional instructions shown above the quiz, in Markdown.'),
            'slug' => $schema->string()->description('URL slug. Defaults to {course-slug}-{lesson-slug}-{step-slug}.'),
            'is_optional' => $schema->boolean()->description('Whether the step can be skipped. Defaults to false.'),
            'require_perfect_score' => $schema->boolean()->description('Learners must score 100% to continue. Defaults to false.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($denied = $this->authorizeWrite($request)) {
            return $denied;
        }

        $validated = $request->validate([
            ...$this->stepRules(),
            'test_name' => ['required', 'string', 'max:255'],
            'require_perfect_score' => ['sometimes', 'boolean'],
            'questions' => ['required', 'array', 'min:1', 'max:100'],
            'questions.*' => ['required', 'array'],
            'questions.*.type' => ['required', 'string', Rule::in(array_keys(self::QUESTION_TYPES))],
            'questions.*.text' => ['required', 'string', 'max:2000', 'not_regex:/</'],
            'questions.*.options' => ['sometimes', 'nullable', 'array', 'max:50'],
            'questions.*.options.*' => ['required', 'string', 'max:255'],
            'questions.*.correct_answer' => ['sometimes', 'nullable'],
            'questions.*.correct_answers' => ['sometimes', 'nullable', 'array'],
            'questions.*.correct_answers.*' => ['required', 'string'],
        ], [
            'questions.*.text.not_regex' => 'Question text cannot contain "<". Labels render as HTML.',
        ]);

        $questions = $this->normalizeQuestions(array_values($validated['questions']));
        $lesson = Lesson::query()->findOrFail($validated['lesson_id']);
        $slug = $this->newStepSlug($lesson, $validated);

        $step = DB::transaction(function () use ($lesson, $validated, $slug, $questions): Step {
            $form = FilamentForm::create([
                'name' => trim((string) $validated['test_name']),
                'permit_guest_entries' => false,
                'locked' => true,
            ]);

            $rubricEntry = [];

            foreach ($questions as $question) {
                $field = FilamentFormField::create([
                    'filament_form_id' => $form->id,
                    'type' => $question['type'],
                    'label' => $question['text'],
                    'required' => $question['required'],
                    'options' => $question['options'],
                ]);

                $rubricEntry[] = [
                    'type' => $question['type']->fieldName(),
                    'field' => $field->label,
                    'answer' => $question['answer'],
                    'field_id' => $field->id,
                ];
            }

            $fieldIds = $form->filamentFormFields()->pluck('id')->all();

            if ($fieldIds !== array_column($rubricEntry, 'field_id')) {
                throw new RuntimeException('Rubric entries do not match the form fields.');
            }

            $rubric = FilamentFormUser::create([
                'filament_form_id' => $form->id,
                'user_id' => null,
                'entry' => $rubricEntry,
            ]);

            $test = Test::create([
                'name' => trim((string) $validated['test_name']),
                'filament_form_id' => $form->id,
                'filament_form_user_id' => $rubric->id,
            ]);

            return $this->createStep($lesson, $validated, $slug, $test, attributes: [
                'require_perfect_score' => (bool) ($validated['require_perfect_score'] ?? false),
            ]);
        });

        $this->auditWrite($request, ['step_id' => $step->id, 'test_id' => $step->material_id]);

        return Response::structured([
            ...$this->serializeStep($step),
            'quiz' => $this->serializeTest(Test::query()->findOrFail($step->material_id)),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $questions
     * @return list<array{type: FilamentFieldTypeEnum, text: string, options: list<string>|null, required: bool, answer: string}>
     *
     * @throws ValidationException
     */
    protected function normalizeQuestions(array $questions): array
    {
        $errors = [];
        $normalized = [];

        foreach ($questions as $index => $question) {
            $type = self::QUESTION_TYPES[$question['type']];
            $key = "questions.{$index}";
            $options = array_values(array_map(fn (string $option): string => trim($option), $question['options'] ?? []));

            if ($type->hasOptions()) {
                if (count($options) < 2) {
                    $errors["{$key}.options"] = 'Question '.($index + 1).' needs at least two options.';

                    continue;
                }

                if (count(array_unique($options)) !== count($options) || in_array('', $options, true)) {
                    $errors["{$key}.options"] = 'Question '.($index + 1).' options must be unique and not blank.';

                    continue;
                }

                if ($type === FilamentFieldTypeEnum::SELECT_MULTIPLE && collect($options)->contains(fn (string $option): bool => str_contains($option, ','))) {
                    $errors["{$key}.options"] = 'Question '.($index + 1).' select_multiple options cannot contain commas; grading splits answers on commas.';

                    continue;
                }
            } elseif ($options !== []) {
                $errors["{$key}.options"] = 'Question '.($index + 1).' is '.$question['type'].' and does not take options.';

                continue;
            }

            $answer = $this->rubricAnswer($type, $question, $options);

            if ($answer === null) {
                $errors["{$key}.correct_answer"] = 'Question '.($index + 1).' is missing a valid correct answer. '.$this->answerHint($question['type']);

                continue;
            }

            $normalized[] = [
                'type' => $type,
                'text' => trim((string) $question['text']),
                'options' => $type->hasOptions() ? $options : null,
                // A required checkbox would force learners to tick it.
                'required' => ! $type->isBool(),
                'answer' => $answer,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * Returns the answer in the same shape the form builder stores for a learner entry.
     *
     * @param  array<string, mixed>  $question
     * @param  list<string>  $options
     */
    protected function rubricAnswer(FilamentFieldTypeEnum $type, array $question, array $options): ?string
    {
        $answer = $question['correct_answer'] ?? null;

        if ($type === FilamentFieldTypeEnum::SELECT_MULTIPLE) {
            $answers = array_values(array_unique(array_map(fn (string $value): string => trim($value), $question['correct_answers'] ?? [])));

            if ($answers === [] || array_diff($answers, $options) !== []) {
                return null;
            }

            return implode(', ', $answers);
        }

        if ($type->isBool()) {
            if ($answer === null || $answer === '') {
                return null;
            }

            $bool = filter_var($answer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            return $bool === null ? null : ($bool ? 'true' : 'false');
        }

        if (! is_string($answer) || trim($answer) === '') {
            return null;
        }

        $answer = trim($answer);

        if ($type->hasOptions() && ! in_array($answer, $options, true)) {
            return null;
        }

        return $answer;
    }

    protected function answerHint(string $type): string
    {
        return match ($type) {
            'select_multiple' => 'Pass correct_answers with one or more of the options.',
            'checkbox', 'toggle' => 'Pass correct_answer "true" or "false".',
            'text' => 'Pass correct_answer with the exact expected text.',
            default => 'Pass correct_answer with one of the options.',
        };
    }
}
