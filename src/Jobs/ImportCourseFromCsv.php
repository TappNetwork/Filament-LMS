<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentLms\Imports\CourseStepsImport;

class ImportCourseFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected string $courseName,
        protected string $filePath
    ) {}

    public function handle(): void
    {
        if (! File::isReadable($this->filePath)) {
            return;
        }

        Excel::import(new CourseStepsImport($this->courseName), $this->filePath);

        File::delete($this->filePath);
    }
}
