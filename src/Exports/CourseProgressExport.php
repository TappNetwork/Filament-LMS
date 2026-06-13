<?php

namespace Tapp\FilamentLms\Exports;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Tapp\FilamentLms\Services\CourseProgressQueryService;

class CourseProgressExport implements FromQuery, WithHeadings, WithMapping
{
    protected Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    public function query(): Builder
    {
        return $this->query;
    }

    public function headings(): array
    {
        $singleName = CourseProgressQueryService::reportUsesSingleNameColumn();
        $nameHeadings = $singleName ? ['Name'] : ['First Name', 'Last Name'];

        return array_merge($nameHeadings, [
            'Email',
            'Course',
            'Status',
            'Progress',
            'Date Started',
            'Date Completed',
            'Completion Date',
        ]);
    }

    public function map($record): array
    {
        $singleName = CourseProgressQueryService::reportUsesSingleNameColumn();
        $nameValues = $singleName
            ? [$record->user_first_name]
            : [$record->user_first_name, $record->user_last_name];

        return array_merge($nameValues, [
            $record->user_email,
            $record->course_name,
            $record->status,
            "{$record->steps_completed} / {$record->total_steps}",
            $record->started_at ? Carbon::parse($record->started_at)->format('Y-m-d') : null,
            $record->completed_at ? Carbon::parse($record->completed_at)->format('Y-m-d') : null,
            $record->completion_date ? Carbon::parse($record->completion_date)->format('Y-m-d') : null,
        ]);
    }
}
