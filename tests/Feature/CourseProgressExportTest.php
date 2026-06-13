<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Tests\Feature;

use Tapp\FilamentLms\Exports\CourseProgressExport;
use Tapp\FilamentLms\Models\CourseProgressReportRow;
use Tapp\FilamentLms\Services\CourseProgressQueryService;

test('course progress export returns the injected report query', function (): void {
    $rawQuery = CourseProgressQueryService::buildQuery();
    $query = CourseProgressReportRow::query()
        ->fromSub($rawQuery, 'report')
        ->where('status', 'Completed');

    $export = new CourseProgressExport($query);

    expect($export->query())->toBe($query);
    expect($export->query()->toSql())->toContain('where "status" = ?');
});
