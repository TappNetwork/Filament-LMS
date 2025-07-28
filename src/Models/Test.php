<?php

namespace Tapp\FilamentLms\Models;

use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;

class Test extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_tests';

    public function form(): BelongsTo
    {
        return $this->belongsTo(FilamentForm::class, 'filament_form_id');
    }

    public function rubric(): BelongsTo
    {
        return $this->belongsTo(FilamentFormUser::class, 'filament_form_user_id');
    }

    public function gradeEntry(FilamentFormUser $entry): float|Exception
    {
        $this->load(['rubric']);

        if (!$this->rubric) {
            return new Exception('No rubric (answer key) has been set up for this test. Please create a rubric first.');
        }

        $totalQuestions = count($this->rubric->entry);

        $exception = $this->checkRubricMismatch($entry);

        if ($exception) {
            return $exception;
        }

        $missCount = 0;

        for ($i = 0; $i < count($entry->entry); $i++) {
            if ($entry->entry[$i]['type'] == 'Select Multiple') {
                if (! self::gradeMultiSelectField($entry->entry[$i]['answer'], $this->rubric->entry[$i]['answer'])) {
                    $missCount++;
                }

                continue;
            }

            if ($entry->entry[$i]['answer'] != $this->rubric->entry[$i]['answer']) {
                $missCount++;
            }
        }

        return round((($totalQuestions - $missCount) / $totalQuestions) * 100, 2);
    }

    public static function gradeMultiSelectField(string $answer, string $correctAnswers): bool
    {
        $answer = array_map('trim', explode(',', $answer));
        $correctAnswers = array_map('trim', explode(',', $correctAnswers));

        if (count($answer) != count($correctAnswers)) {
            return false;
        }

        foreach ($answer as $a) {
            if (! in_array($a, $correctAnswers)) {
                return false;
            }
        }

        return true;
    }

    public function gradedKeyValueEntry(FilamentFormUser $entry): array|Exception
    {
        $this->load(['rubric']);

        if (!$this->rubric) {
            return new Exception('No rubric (answer key) has been set up for this test. Please create a rubric first.');
        }

        $exception = $this->checkRubricMismatch($entry);

        if ($exception) {
            return $exception;
        }

        $ret = [];

        for ($i = 0; $i < count($entry->entry); $i++) {
            $field = [];

            $field['answer'] = $entry->entry[$i]['answer'];

            if ($entry->entry[$i]['type'] == 'Select Multiple') {
                $field['correct'] = self::gradeMultiSelectField($entry->entry[$i]['answer'], $this->rubric->entry[$i]['answer']);
            } else {
                $field['correct'] = ($entry->entry[$i] == $this->rubric->entry[$i]);
            }

            $field['correct_answer'] = $this->rubric->entry[$i]['answer'];

            $ret[$entry->entry[$i]['field']] = $field;
        }

        return $ret;
    }

    private function checkRubricMismatch(FilamentFormUser $entry): bool|Exception
    {
        $this->load(['rubric']);

        if (!$this->rubric) {
            return new Exception('No rubric (answer key) has been set up for this test. Please create a rubric first.');
        }

        $totalQuestions = count($this->rubric->entry);

        if ($entry->filament_form_id != $this->rubric->filament_form_id) {
            return new Exception('rubric and assessment entry are not referencing the same form');
        }

        if (count($entry->entry) != $totalQuestions) {
            return new Exception('rubric mismatch from assessment entry');
        }

        return false;
    }
}
