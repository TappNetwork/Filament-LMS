<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

final class CourseEvaluationEntriesSheet implements FromArray, WithHeadings, WithTitle
{
    /**
     * @param  list<array{first_name: string, last_name: string, email: string, course: string, submitted_at: string, answers: array<string, string>}>  $rows
     * @param  list<string>  $fieldHeadings
     */
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
        private readonly bool $singleName,
        private readonly array $fieldHeadings,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        $nameHeadings = $this->singleName
            ? ['Name']
            : ['First Name', 'Last Name'];

        return array_merge($nameHeadings, [
            'Email',
            'Course',
            'Submitted At',
        ], $this->fieldHeadings);
    }

    public function array(): array
    {
        return array_map(function (array $row): array {
            $identity = $this->singleName
                ? [$row['first_name']]
                : [$row['first_name'], $row['last_name']];

            $mapped = array_merge($identity, [
                $row['email'],
                $row['course'],
                $row['submitted_at'],
            ]);

            foreach ($this->fieldHeadings as $heading) {
                $mapped[] = $row['answers'][$heading] ?? '';
            }

            return $mapped;
        }, $this->rows);
    }
}
