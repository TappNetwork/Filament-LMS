<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Tapp\FilamentLms\Imports\CourseStepsImport;

class ImportCourseFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $courseName,
        protected string $storedPath
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('local');

        if (! $disk->exists($this->storedPath)) {
            throw new RuntimeException("Course import file does not exist on local disk: {$this->storedPath}");
        }

        $filePath = $disk->path($this->storedPath);

        if (! is_readable($filePath)) {
            throw new RuntimeException("Course import file is not readable: {$filePath}");
        }

        Excel::import(new CourseStepsImport($this->courseName), $filePath);

        $disk->delete($this->storedPath);
    }
}
