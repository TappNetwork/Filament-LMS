<?php

namespace Tapp\FilamentLms\Exports;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;

class CourseProgressExporter extends Exporter
{
    // This exporter doesn't have a direct model 
    // since the reporting data is from a custom query
    protected static ?string $model = null;

    public static function getColumns(): array
    {
        return [
            // ExportColumn::make('user_first_name')
            //     ->label('First Name'),

            // ExportColumn::make('user_last_name')
            //     ->label('Last Name'),

            ExportColumn::make('user_email')
                ->label('User Email'),

            // ExportColumn::make('course_name')
            //     ->label('Course'),

            // ExportColumn::make('status')
            //     ->label('Status'),

            // ExportColumn::make('steps_completed')
            //     ->label('Steps Completed'),

            // ExportColumn::make('total_steps')
            //     ->label('Total Steps'),

            // ExportColumn::make('started_at')
            //     ->label('Date Started'),

            // ExportColumn::make('completed_at')
            //     ->label('Date Completed'),
                
            // ExportColumn::make('completion_date')
            //     ->label('Completion Date'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your course progress export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';
        
        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }
        
        return $body;
    }

    // /**
    //  * Map the given record into a row that can be exported.
    //  */
    // public static function getFormattedRecord(Model|array $record): array
    // {
    //     // If the record is an array (from the custom query), return it as is
    //     if (is_array($record)) {
    //         // For array records, format the progress as a string
    //         if (isset($record['steps_completed']) && isset($record['total_steps'])) {
    //             $record['steps_completed'] = "{$record['steps_completed']} / {$record['total_steps']}";
    //         }
            
    //         return $record;
    //     }

    //     // For Model records (unlikely in this case), return an empty array
    //     return [];
    // }
} 