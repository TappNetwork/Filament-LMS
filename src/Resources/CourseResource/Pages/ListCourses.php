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
                        Str::uuid().'.csv'
                    );

                    if ($storedPath === false) {
                        Notification::make()
                            ->title('Import failed')
                            ->body('Could not store the uploaded file.')
                            ->danger()
                            ->send();

                        return;
                    }

                    ImportCourseFromCsv::dispatch($courseName, Storage::path($storedPath));

                    Notification::make()
                        ->title('Import queued')
                        ->body("Course \"{$courseName}\" will be imported in the background. You can continue working while the import runs.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
