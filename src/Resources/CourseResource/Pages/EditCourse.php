<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Support\CertificateBuilder;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_certificate_template')
                ->label('Create Certificate Template')
                ->icon('heroicon-o-document-duplicate')
                ->visible(fn (): bool => CertificateBuilder::canCreateTemplate(
                    auth()->user(),
                    $this->getRecord(),
                ))
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255)
                        ->default(fn (): string => $this->getRecord()->name.' Certificate'),
                ])
                ->action(function (array $data): void {
                    $this->createAndAssociateCertificateTemplate((string) $data['name']);
                }),
            Action::make('edit_certificate_template')
                ->label('Edit Certificate Template')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => CertificateBuilder::templateEditUrl($this->getRecord()) ?? '#')
                ->visible(fn (): bool => CertificateBuilder::canEditTemplate(
                    auth()->user(),
                    $this->getRecord(),
                )),
            DeleteAction::make(),
        ];
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

        /** @var Course $course */
        $course = $this->getRecord();
        $course->update([
            'certificate_template_id' => $template->getKey(),
        ]);

        $resource = CertificateBuilder::templateResource();

        if ($resource !== null) {
            $this->redirect($resource::getUrl('edit', ['record' => $template]));
        }
    }
}
