<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Concerns\RemembersErrorBag;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Pages\Dashboard;
use Tapp\FilamentLms\Pages\Step;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Resources\CourseResource\Pages\Concerns\HasCourseEditorTabs;
use Tapp\FilamentLms\Support\CertificateBuilder;
use Tapp\FilamentLms\Support\CourseEditor;

class EditCourse extends EditRecord
{
    use HasCourseEditorTabs;
    use RemembersErrorBag;

    protected static string $resource = CourseResource::class;

    public function areFormActionsSticky(): bool
    {
        return CourseEditor::isBuilder() || parent::areFormActionsSticky();
    }

    public function getBreadcrumbs(): array
    {
        if (! CourseEditor::isBuilder()) {
            return parent::getBreadcrumbs();
        }

        return [
            $this->getResourceUrl() => __('filament-lms::course-editor.actions.courses'),
            $this->courseRecord()->name,
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (CourseEditor::isBuilder()) {
            $data['slug_is_locked'] = ($data['slug'] ?? '') !== Str::slug((string) ($data['name'] ?? ''));
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('filament-lms::course-editor.actions.preview'))
                ->color('gray')
                ->outlined()
                ->url(fn (): string => $this->learnerUrl())
                ->openUrlInNewTab()
                ->visible(fn (): bool => CourseEditor::isBuilder()),
            Action::make('create_certificate_template')
                ->label('Create Certificate Template')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => CertificateBuilder::canCreateTemplate(
                    auth()->user(),
                    $this->courseRecord(),
                ))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => $this->courseRecord()->name.' Certificate'),
                ])
                ->action(function (array $data): void {
                    $this->createAndAssociateCertificateTemplate((string) $data['name']);
                }),
            Action::make('edit_certificate_template')
                ->label('Edit Certificate Template')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => CertificateBuilder::templateEditUrl($this->courseRecord()) ?? '#')
                ->visible(fn (): bool => CertificateBuilder::canEditTemplate(
                    auth()->user(),
                    $this->courseRecord(),
                )),
            DeleteAction::make(),
        ];
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if (! CourseEditor::isBuilder()) {
            return parent::getFormActions();
        }

        return [
            $this->getSaveFormAction()->label(__('filament-lms::course-editor.actions.save_changes')),
            Action::make('saveAndPreview')
                ->label(__('filament-lms::course-editor.actions.save_and_preview'))
                ->color('gray')
                ->action(function (): void {
                    $this->save(shouldRedirect: false);
                    $this->js('window.open('.Js::from($this->learnerUrl()).', "_blank", "noopener")');
                }),
            $this->getCancelFormAction()->label(__('filament-lms::course-editor.actions.cancel')),
        ];
    }

    public function learnerUrl(): string
    {
        $course = $this->courseRecord();
        $step = $course->firstStep();

        if ($step === null) {
            return Dashboard::getUrl(panel: 'lms');
        }

        $step->loadMissing('lesson');

        return Step::getUrl([
            $course->slug,
            $step->lesson->slug,
            $step->slug,
        ], panel: 'lms');
    }

    public function createAndAssociateCertificateTemplate(string $name): void
    {
        if (! CertificateBuilder::enabled()) {
            return;
        }

        $templateClass = CertificateBuilder::TEMPLATE_MODEL;
        $layoutClass = CertificateBuilder::LAYOUT_CLASS;
        $tokenSet = CertificateBuilder::tokenSet();

        $layout = class_exists($layoutClass) ? $layoutClass::default($tokenSet) : [];

        $template = $templateClass::query()->create([
            'name' => $name,
            'token_set' => $tokenSet,
            'layout' => $layout,
        ]);

        $this->courseRecord()->update([
            'certificate_template_id' => $template->getKey(),
        ]);

        $resource = CertificateBuilder::templateResource();

        if ($resource !== null) {
            $this->redirect($resource::getUrl('edit', ['record' => $template]));
        }
    }

    private function courseRecord(): Course
    {
        /** @var Course $course */
        $course = $this->getRecord();

        return $course;
    }
}
