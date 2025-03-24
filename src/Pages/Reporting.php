<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tapp\FilamentLms\Exports\CourseProgressExporter;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\StepUser;

class Reporting extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament-lms::pages.reporting';

    protected static ?string $title = 'Reports';

    protected static ?string $navigationLabel = 'Reporting';

    protected static ?string $slug = 'reporting';

    protected static ?string $navigationGroup = 'LMS';

    public function getTableRecordKey(array|\Illuminate\Database\Eloquent\Model $record): string
    {
        if ($record instanceof \Illuminate\Database\Eloquent\Model) {
            $key = $record->getKey();
        }

        // For array records, create a unique composite key from user_id and course_id
        return $key ?? "user_{$record['user_id']}_course_{$record['course_id']}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // We need to start with StepUser model to get completion data
                StepUser::query()
                    ->join('users', 'lms_step_user.user_id', '=', 'users.id')
                    ->join('lms_steps', 'lms_step_user.step_id', '=', 'lms_steps.id')
                    ->join('lms_lessons', 'lms_steps.lesson_id', '=', 'lms_lessons.id')
                    ->join('lms_courses', 'lms_lessons.course_id', '=', 'lms_courses.id')
                    ->select([
                        'users.id as user_id',
                        'users.first_name as user_first_name',
                        'users.last_name as user_last_name',
                        'users.email as user_email',
                        'lms_courses.id as course_id',
                        'lms_courses.name as course_name',
                        DB::raw('MIN(lms_step_user.created_at) as started_at'),
                        DB::raw('MAX(lms_step_user.completed_at) as completed_at'),
                        DB::raw('CASE 
                                WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) = 
                                (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id) 
                                THEN MAX(lms_step_user.completed_at) 
                                ELSE NULL 
                            END as completion_date'),
                        DB::raw('COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) as steps_completed'),
                        DB::raw('(SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id) as total_steps'),
                        DB::raw('CASE 
                                WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) = 
                                (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id) 
                                THEN "Completed" 
                                ELSE "In Progress" 
                            END as status'),
                    ])
                    ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.email', 'lms_courses.id', 'lms_courses.name')
            )
            ->columns([
                TextColumn::make('user_first_name')
                    ->label('First Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user_last_name')
                    ->label('Last Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user_email')
                    ->label('User Email')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('course_name')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Completed' => 'success',
                        'In Progress' => 'warning',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('steps_completed')
                    ->label('Progress')
                    ->formatStateUsing(fn ($record) => "{$record['steps_completed']} / {$record['total_steps']}")
                    ->sortable(),

                TextColumn::make('started_at')
                    ->label('Date Started')
                    ->date()
                    ->sortable(),

                TextColumn::make('completed_at')
                    ->label('Date Completed')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('date_range')
                    ->form([
                        DatePicker::make('completed_from')
                            ->label('Completed From'),
                        DatePicker::make('completed_until')
                            ->label('Completed Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('lms_step_user.completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('lms_step_user.completed_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['completed_from'] ?? null) {
                            $indicators['completed_from'] = 'Completed from '.Carbon::parse($data['completed_from'])->toFormattedDateString();
                        }

                        if ($data['completed_until'] ?? null) {
                            $indicators['completed_until'] = 'Completed until '.Carbon::parse($data['completed_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),

                SelectFilter::make('course_id')
                    ->label('Course')
                    ->options(fn () => Course::pluck('name', 'id')->toArray())
                    ->attribute('course_id'),

                SelectFilter::make('user_id')
                    ->label('User')
                    ->options(function () {
                        return DB::table('users')->pluck('email', 'id')->toArray();
                    })
                    ->searchable()
                    ->attribute('user_id'),
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->exporter(CourseProgressExporter::class),
            ])
            ->headerActions([
                Tables\Actions\ExportAction::make()
                    ->exporter(CourseProgressExporter::class),
            ])
            ->defaultSort(function (Builder $query) {
                // Use raw SQL for ordering to avoid ONLY_FULL_GROUP_BY issues
                return $query->orderByRaw('MAX(lms_step_user.completed_at) DESC');
            });
    }
}
