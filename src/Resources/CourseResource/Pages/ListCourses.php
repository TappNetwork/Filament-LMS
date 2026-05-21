<?php

namespace Tapp\FilamentLms\Resources\CourseResource\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tapp\FilamentLms\Jobs\ImportCommonCartridgeJob;
use Tapp\FilamentLms\Jobs\ImportCourseFromCsv;
use Tapp\FilamentLms\Resources\CourseResource;

class ListCourses extends ListRecords
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
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
                ->action(function (array $data): void {
                    $file = $data['file'];
                    $courseName = trim($data['course_name']);

                    if (! $file instanceof UploadedFile) {
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
                    FileUpload::make('file')
                        ->label('ZIP package')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes([
                            'application/zip',
                            'application/x-zip-compressed',
                        ])
                        ->maxSize(512000)
                        ->helperText('Upload a SCORM 1.2 or Articulate Storyline / Rise ZIP export.'),
                ])
                ->action(function (array $data): void {
                    $file = $data['file'];

                    if (! $file instanceof UploadedFile) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not read the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $disk = (string) config('filament-lms.common_cartridge_import.storage_disk', 'local');
                    $directory = (string) config('filament-lms.common_cartridge_import.storage_directory', 'filament-lms/cartridge-imports');
                    $storedPath = $file->storeAs(
                        $directory,
                        Str::uuid().'.zip',
                        $disk
                    );

                    if ($storedPath === false) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not store the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $absolutePath = Storage::disk($disk)->path($storedPath);
                    $userId = auth()->id();

                    if ($userId === null) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('You must be signed in to import packages.')
                            ->danger()
                            ->send();

                        return;
                    }

                    ImportCommonCartridgeJob::dispatch($absolutePath, $userId);

                    Notification::make()
                        ->title('Import queued')
                        ->body('The SCORM package will be imported in the background. You will receive a notification when it completes.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
