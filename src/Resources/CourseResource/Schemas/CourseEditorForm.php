<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Resources\CourseResource\Schemas;

use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Enums\CompletionMode;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\CourseEvaluationService;
use Tapp\FilamentLms\Support\CertificateBuilder;

final class CourseEditorForm
{
    /**
     * Flat field list used when course_editor.mode is legacy.
     *
     * @return array<int, mixed>
     */
    public static function legacyComponents(): array
    {
        return [
            self::nameField(builder: false),
            self::externalIdField(),
            self::slugField(builder: false),
            self::imageField(),
            self::descriptionField(),
            self::requiredTestPercentageField(builder: false),
            self::certificateTemplateField(),
            self::embeddedPlayerField(builder: false),
            self::completionModeField(),
            self::isPrivateField(builder: false),
            self::evaluationCourseField(builder: false),
            self::creditsRepeater(),
        ];
    }

    /**
     * Details tab layout used when course_editor.mode is builder.
     *
     * @return array<int, mixed>
     */
    public static function builderComponents(): array
    {
        return [
            self::slugLockField(),
            Grid::make(['default' => 1, 'lg' => 3])
                ->schema([
                    Section::make(__('filament-lms::course-editor.sections.general.heading'))
                        ->description(__('filament-lms::course-editor.sections.general.description'))
                        ->columnSpan(['lg' => 2])
                        ->schema([
                            self::nameField(builder: true),
                            self::descriptionField()->rows(4),
                            Section::make(__('filament-lms::course-editor.sections.advanced.heading'))
                                ->schema([
                                    self::slugField(builder: true),
                                    self::externalIdField(),
                                ])
                                ->columns(2)
                                ->collapsible()
                                ->collapsed(),
                        ]),
                    Grid::make(1)
                        ->schema([
                            Section::make(__('filament-lms::course-editor.sections.visibility.heading'))
                                ->description(__('filament-lms::course-editor.sections.visibility.description'))
                                ->schema([
                                    self::isPrivateField(builder: true),
                                ]),
                            Section::make(__('filament-lms::course-editor.sections.image.heading'))
                                ->schema([
                                    self::imageField()->hiddenLabel(),
                                ]),
                            Section::make(__('filament-lms::course-editor.sections.credits.heading'))
                                ->description(__('filament-lms::course-editor.sections.credits.description'))
                                ->schema([
                                    self::creditsRepeater(),
                                ])
                                ->visible(fn (): bool => (bool) config('filament-lms.credits_enabled', false)),
                        ])
                        ->columnSpan(['lg' => 1]),
                    Section::make(__('filament-lms::course-editor.sections.completion.heading'))
                        ->description(__('filament-lms::course-editor.sections.completion.description'))
                        ->columnSpanFull()
                        ->columns(2)
                        ->schema([
                            self::completionModeField(),
                            self::requiredTestPercentageField(builder: true),
                            self::embeddedPlayerField(builder: true)->columnSpanFull(),
                            self::evaluationCourseField(builder: true)->columnSpanFull(),
                            self::certificateTemplateField()->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    public static function slugLockField(): Hidden
    {
        return Hidden::make('slug_is_locked')
            ->dehydrated(false)
            ->default(fn (?Course $record): bool => $record !== null && $record->slug !== Str::slug((string) $record->name));
    }

    public static function nameField(bool $builder): TextInput
    {
        $field = TextInput::make('name')
            ->label(__('filament-lms::course-editor.fields.name.label'))
            ->live(onBlur: true)
            ->scopedUnique(ignoreRecord: true)
            ->required();

        if ($builder) {
            return $field->afterStateUpdated(function (Set $set, Get $get, ?string $state, string $operation): void {
                if (! $get('slug_is_locked')) {
                    $set('slug', Str::slug($state ?? ''));
                }

                if ($operation === 'create' || empty($get('external_id'))) {
                    $set('external_id', Str::slug($state ?? '', '_'));
                }
            });
        }

        return $field->afterStateUpdated(function (Set $set, ?string $state, string $operation, $get): void {
            // Always update slug when name changes
            $set('slug', Str::slug($state));

            // Only auto-generate external_id on create or if it's empty.
            // Use slug with underscore so acronyms stay together (e.g. DNS Cloudflare -> dns_cloudflare not d_n_s_cloudflare).
            if ($operation === 'create' || empty($get('external_id'))) {
                $set('external_id', Str::slug($state ?? '', '_'));
            }
        });
    }

    public static function externalIdField(): TextInput
    {
        return TextInput::make('external_id')
            ->label(__('filament-lms::course-editor.fields.external_id.label'))
            ->helperText(__('filament-lms::course-editor.fields.external_id.helper'))
            ->required()
            ->scopedUnique(ignoreRecord: true)
            ->rules([
                'regex:/^[a-z][a-z0-9_]*$/',
                'max:100',
            ])
            ->validationMessages([
                'regex' => __('filament-lms::course-editor.fields.external_id.regex'),
                'max' => __('filament-lms::course-editor.fields.external_id.max'),
            ]);
    }

    public static function slugField(bool $builder): TextInput
    {
        $field = TextInput::make('slug')
            ->label(__('filament-lms::course-editor.fields.slug.label'))
            ->helperText(__('filament-lms::course-editor.fields.slug.course_helper'))
            ->scopedUnique(ignoreRecord: true)
            ->required();

        if (! $builder) {
            return $field;
        }

        return $field
            ->live(onBlur: true)
            ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                $set('slug_is_locked', Str::slug((string) $get('name')) !== (string) $state);
            });
    }

    public static function imageField(): SpatieMediaLibraryFileUpload
    {
        return SpatieMediaLibraryFileUpload::make('image')
            ->label(__('filament-lms::course-editor.fields.image.label'))
            ->helperText(__('filament-lms::course-editor.fields.image.helper'))
            ->collection('courses')
            ->image();
    }

    public static function descriptionField(): Textarea
    {
        return Textarea::make('description')
            ->label(__('filament-lms::course-editor.fields.description.label'));
    }

    public static function requiredTestPercentageField(bool $builder): TextInput
    {
        $field = TextInput::make('required_test_percentage')
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->default(0)
            ->nullable();

        if (! $builder) {
            return $field
                ->label('Required Average Test Score (percentage)')
                ->helperText('User will not be able to complete the course until they have met this requirement.');
        }

        return $field
            ->label(__('filament-lms::course-editor.fields.required_test_percentage.label'))
            ->suffix('%')
            ->helperText(__('filament-lms::course-editor.fields.required_test_percentage.helper'))
            ->hintIcon(
                'heroicon-o-information-circle',
                __('filament-lms::course-editor.fields.required_test_percentage.hint'),
            );
    }

    public static function certificateTemplateField(): Select
    {
        return Select::make('certificate_template_id')
            ->label(__('filament-lms::course-editor.fields.certificate_template.label'))
            ->helperText(__('filament-lms::course-editor.fields.certificate_template.helper'))
            ->options(function (): array {
                $model = CertificateBuilder::TEMPLATE_MODEL;

                return $model::query()
                    ->where('token_set', CertificateBuilder::tokenSet())
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all();
            })
            ->default(fn (): ?int => CertificateBuilder::defaultTemplateId())
            ->hint(function (?Course $record) {
                if ($record && $record->id) {
                    $link = route('filament-lms::certificates.show', ['course' => $record->id, 'user' => auth()->id()]);

                    $preview = e(__('filament-lms::course-editor.fields.certificate_template.preview'));

                    return new HtmlString("<a rel='noopener noreferrer' target='_blank' href='{$link}'>{$preview}</a>");
                }

                return null;
            })
            ->searchable()
            ->preload()
            ->required()
            ->visible(fn (): bool => CertificateBuilder::enabled());
    }

    public static function embeddedPlayerField(bool $builder): Checkbox|Toggle
    {
        if (! $builder) {
            return Checkbox::make('embedded_player')
                ->label('Embedded player mode')
                ->helperText('Hides LMS step sidebar and uses the SCORM/HTML5 package as the primary navigation.');
        }

        return Toggle::make('embedded_player')
            ->label(new HtmlString(view('filament-lms::filament.course-editor.label-hint', [
                'label' => __('filament-lms::course-editor.fields.embedded_player.label'),
                'tooltip' => __('filament-lms::course-editor.fields.embedded_player.hint'),
            ])->toHtml()))
            ->helperText(__('filament-lms::course-editor.fields.embedded_player.helper'));
    }

    public static function completionModeField(): Select
    {
        return Select::make('completion_mode')
            ->label(__('filament-lms::course-editor.fields.completion_mode.label'))
            ->options([
                CompletionMode::Native->value => __('filament-lms::course-editor.fields.completion_mode.native'),
                CompletionMode::Scorm12->value => __('filament-lms::course-editor.fields.completion_mode.scorm12'),
                CompletionMode::Html5->value => __('filament-lms::course-editor.fields.completion_mode.html5'),
            ])
            ->default(CompletionMode::Native->value)
            ->required();
    }

    public static function isPrivateField(bool $builder): Checkbox|Toggle
    {
        if (! $builder) {
            return Checkbox::make('is_private')
                ->label('Private Course')
                ->helperText('Private courses are only visible to assigned users and LMS admins');
        }

        return Toggle::make('is_private')
            ->label(__('filament-lms::course-editor.fields.is_private.label'))
            ->helperText(__('filament-lms::course-editor.fields.is_private.helper'));
    }

    public static function evaluationCourseField(bool $builder): Select
    {
        $field = Select::make('evaluation_course_id')
            ->label(__('filament-lms::course-editor.fields.evaluation_course.label'))
            ->relationship(
                'evaluationCourse',
                'name',
                modifyQueryUsing: function (Builder $query, Get $get, $livewire): void {
                    $recordId = $livewire->record?->id;
                    if ($recordId !== null) {
                        $query->where('id', '!=', $recordId);
                    }

                    $query->whereNull('evaluation_course_id')
                        ->where('is_private', true);
                },
            )
            ->searchable()
            ->preload()
            ->nullable()
            ->visible(function (?Course $record): bool {
                if (! config('filament-lms.evaluations.enabled', false)) {
                    return false;
                }

                if ($record === null) {
                    return true;
                }

                return ! app(CourseEvaluationService::class)->isEvaluationCourse($record);
            })
            ->rules([
                fn (?Course $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                    if ($value === null) {
                        return;
                    }

                    $service = app(CourseEvaluationService::class);

                    if (! $service->canUseEvaluationCourseId($record, $value)) {
                        $fail(__('filament-lms::course-editor.fields.evaluation_course.invalid'));
                    }
                },
            ]);

        if ($builder) {
            return $field->hintIcon(
                'heroicon-o-information-circle',
                __('filament-lms::course-editor.fields.evaluation_course.hint'),
            );
        }

        return $field->helperText(__('filament-lms::course-editor.fields.evaluation_course.hint'));
    }

    public static function creditsRepeater(): Repeater
    {
        return Repeater::make('courseCreditCategories')
            ->relationship()
            ->label(config('filament-lms.credits_repeater_label', 'Credits'))
            ->schema([
                Select::make('credit_category_id')
                    ->label(__('filament-lms::course-editor.fields.credit_category.label'))
                    ->relationship('creditCategory', 'name', modifyQueryUsing: fn ($query) => $query->orderBy('name'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                TextInput::make('credits')
                    ->label(__('filament-lms::course-editor.fields.credits.label'))
                    ->numeric()
                    ->required()
                    ->minValue(0.5)
                    ->step(0.5),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel(config('filament-lms.credits_add_action_label', 'Add credits'))
            ->visible(fn (): bool => config('filament-lms.credits_enabled', false));
    }
}
