<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Livewire;

use Filament\Forms\Components\Radio;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Support\HtmlString;
use Tapp\FilamentFormBuilder\Enums\FilamentFieldTypeEnum;
use Tapp\FilamentFormBuilder\Livewire\FilamentForm\Show;
use Tapp\FilamentFormBuilder\Models\FilamentFormField;

/**
 * Learner-facing test form: single-choice {@see FilamentFieldTypeEnum::SELECT} fields render as
 * {@see Radio} so knowledge checks match card-style MCQ UX while preserving stored field type
 * ("Select") for grading and rubrics.
 */
class LmsTestFormShow extends Show
{
    public function getFormSchema(): array
    {
        $schema = [];

        /** @var FilamentFormField $fieldData */
        foreach ($this->filamentForm->filamentFormFields as $fieldData) {
            $componentClass = $fieldData->type === FilamentFieldTypeEnum::SELECT
                ? Radio::class
                : $fieldData->type->className();

            $filamentField = $componentClass::make((string) $fieldData->getKey());

            $filamentField = $this->parseField($filamentField, $fieldData->toArray());

            if ($fieldData->type === FilamentFieldTypeEnum::SELECT_MULTIPLE && $filamentField instanceof Select) {
                $filamentField = $filamentField
                    ->multiple()
                    ->live()
                    ->required()
                    ->default([]);
            } elseif ($fieldData->type === FilamentFieldTypeEnum::CHECKBOX) {
                $filamentField = $filamentField
                    ->default(false);
            } elseif ($fieldData->type === FilamentFieldTypeEnum::CHECKBOX_LIST) {
                $filamentField = $filamentField
                    ->default([]);
            } elseif ($fieldData->type === FilamentFieldTypeEnum::REPEATER) {
                $filamentField = $filamentField
                    ->schema(function () use ($fieldData) {
                        $repeaterSchema = [];
                        foreach ($fieldData->schema ?? [] as $index => $subField) {
                            $subFieldId = $subField['id'] ?? $fieldData->id.'_'.$subField['type'].'_'.$index;
                            $subFieldComponent = FilamentFieldTypeEnum::fromString($subField['type'])->className()::make($subFieldId);

                            if (isset($subField['label'])) {
                                $subFieldComponent = $subFieldComponent->label(new HtmlString($subField['label']));
                            }

                            if (isset($subField['required']) && $subField['required']) {
                                $subFieldComponent = $subFieldComponent->required();
                            }

                            if (isset($subField['options'])) {
                                $subFieldComponent = $subFieldComponent->options(array_combine($subField['options'], $subField['options']));
                            }

                            if (isset($subField['hint'])) {
                                $subFieldComponent = $subFieldComponent->hint($subField['hint']);
                            }

                            if (isset($subField['rules'])) {
                                $subFieldComponent = $subFieldComponent->rules($subField['rules']);
                            }

                            $repeaterSchema[] = $subFieldComponent;
                        }

                        return $repeaterSchema;
                    })
                    ->default([])
                    ->live();
            }

            if ($fieldData->type === FilamentFieldTypeEnum::RICH_EDITOR && $filamentField instanceof RichEditor) {
                $filamentField = $filamentField->disableToolbarButtons(['attachFiles']);
            }

            if ($fieldData->type === FilamentFieldTypeEnum::SELECT && $filamentField instanceof Radio) {
                $filamentField = $filamentField
                    ->inline(false)
                    ->extraFieldWrapperAttributes([
                        'class' => 'lms-knowledge-check-radio-field',
                    ]);
            }

            $schema[] = $filamentField;
        }

        return $schema;
    }

    public function render()
    {
        return view('filament-lms::livewire.lms-test-form-show');
    }
}
