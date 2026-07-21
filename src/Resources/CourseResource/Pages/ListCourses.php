<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;
use SpykApp\UppyUpload\Forms\Components\UppyUpload;
use Tapp\FilamentLms\Jobs\ImportCourseFromCsv;
use Tapp\FilamentLms\Resources\CourseResource;
use Tapp\FilamentLms\Services\CommonCartridge\CartridgeImportStarter;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        $maxUploadSizeKb = (int) config('filament-lms.common_cartridge_import.max_upload_size_kb', 512000);

        return [
            Action::make('import_course')
                ->label('Import Course')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('file')
                        ->label('CSV file')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/csv',
                            'text/plain',
                            'application/vnd.ms-excel',
                        ])
                        ->maxSize(10240)
                        ->helperText('Upload a CSV with columns: "Step Name", "Lesson Name", "Url", "Script", "Slides (Image)", "Video (Audio + Image)"'),
                    TextInput::make('course_name')
                        ->label('Course name')
                        ->required()
                        ->maxLength(255)
                        ->helperText('Name of the new course.'),
                ])
                ->action(function (array $data, CartridgeImportStarter $importStarter): void {
                    $file = $importStarter->resolveUploadedFile($data['file'] ?? null);
                    $courseName = trim($data['course_name']);

                    if ($file === null) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not read the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $storedPath = $file->storeAs(
                        'filament-lms/course-imports',
                        Str::uuid().'.csv',
                        'local'
                    );

                    if ($storedPath === false) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not store the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    ImportCourseFromCsv::dispatch($courseName, $storedPath);

                    Notification::make()
                        ->title('Import queued')
                        ->body("Course \"{$courseName}\" will be imported in the background. You can continue working while the import runs.")
                        ->success()
                        ->send();
                }),
            Action::make('import_cartridge')
                ->label('Import SCORM Package')
                ->icon('heroicon-o-archive-box-arrow-down')
                ->schema([
                    $this->scormPackageUploadField($maxUploadSizeKb),
                ])
                ->action(function (array $data, CartridgeImportStarter $importStarter): void {
                    $absolutePath = $importStarter->stageUploadedCartridge($data['file'] ?? null);

                    if ($absolutePath === null) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not read the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $userId = auth()->id();

                    if ($userId === null) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('You must be signed in to import packages.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $tenantId = config('filament-lms.tenancy.enabled')
                        ? Filament::getTenant()?->getKey()
                        : null;

                    $importStarter->dispatch($absolutePath, $userId, $tenantId);

                    Notification::make()
                        ->title('Import queued')
                        ->body('The SCORM package will be imported in the background. You will receive a notification when it completes.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    protected function scormPackageUploadField(int $maxUploadSizeKb): Field
    {
        $helperText = 'Upload a SCORM 1.2 or Articulate Storyline / Rise ZIP export. Large packages may take a minute to upload.';
        $acceptedFileTypes = [
            'application/zip',
            'application/x-zip-compressed',
            'application/octet-stream',
        ];

        if (CartridgeImportStarter::usesMultipartUpload()) {
            return $this->makeUppyScormPackageUploadField($maxUploadSizeKb, $helperText, $acceptedFileTypes);
        }

        return FileUpload::make('file')
            ->label('ZIP package')
            ->required()
            ->storeFiles(false)
            ->acceptedFileTypes($acceptedFileTypes)
            ->rules(['mimes:zip'])
            ->maxSize($maxUploadSizeKb)
            ->helperText($helperText);
    }

    /**
     * Build the Uppy chunked upload field (optional spykapps/filament-uppy-upload).
     *
     * @param  list<string>  $acceptedFileTypes
     */
    protected function makeUppyScormPackageUploadField(int $maxUploadSizeKb, string $helperText, array $acceptedFileTypes): Field
    {
        $importStarter = app(CartridgeImportStarter::class);
        $chunkSize = (int) config('filament-lms.common_cartridge_import.multipart_upload.chunk_size', 5 * 1024 * 1024);

        return UppyUpload::make('file')
            ->label('ZIP package')
            ->required()
            ->disk($importStarter->storageDisk())
            ->directory($importStarter->storageDirectory())
            ->acceptedFileTypes($acceptedFileTypes)
            ->maxFileSize($maxUploadSizeKb * 1024)
            ->chunkSize($chunkSize)
            ->single()
            ->webcam(false)
            ->screenCapture(false)
            ->audio(false)
            ->imageEditor(false)
            ->note($helperText)
            ->helperText($helperText);
    }
}
