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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentLms\Exports\CourseProgressExport;
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

    public static function canAccess(): bool
    {
        return Auth::check() && Gate::allows('viewLmsReporting');
    }

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
                        DB::raw('CASE
                                WHEN COUNT(DISTINCT CASE WHEN lms_step_user.completed_at IS NOT NULL THEN lms_step_user.step_id END) =
                                (SELECT COUNT(DISTINCT s.id) FROM lms_steps s JOIN lms_lessons l ON s.lesson_id = l.id WHERE l.course_id = lms_courses.id)
                                THEN MAX(lms_step_user.completed_at)
                                ELSE NULL
                            END as completed_at'),
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
                    ->sortable(),

                TextColumn::make('user_last_name')
                    ->label('Last Name')
                    ->sortable(),

                TextColumn::make('user_email')
                    ->label('User Email')
                    ->sortable(),

                TextColumn::make('course_name')
                    ->label('Course')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(function (string $state): string {
                        if ($state === 'Completed') {
                            return 'success';
                        }
                        if ($state === 'In Progress') {
                            return 'warning';
                        }

                        return 'gray';
                    })
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

                SelectFilter::make('status')
                    ->options([
                        'Completed' => 'Completed',
                        'In Progress' => 'In Progress',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when($data['value'], function ($query, $status) {
                            if ($status === 'Completed') {
                                return $query->whereRaw('(SELECT COUNT(DISTINCT s2.step_id) FROM lms_step_user s2
                                    WHERE s2.user_id = lms_step_user.user_id
                                    AND s2.completed_at IS NOT NULL
                                    AND s2.step_id IN (
                                        SELECT s3.id FROM lms_steps s3
                                        JOIN lms_lessons l3 ON s3.lesson_id = l3.id
                                        WHERE l3.course_id = lms_courses.id
                                    )) = (
                                    SELECT COUNT(DISTINCT s4.id)
                                    FROM lms_steps s4
                                    JOIN lms_lessons l4 ON s4.lesson_id = l4.id
                                    WHERE l4.course_id = lms_courses.id
                                )');
                            } else {
                                return $query->whereRaw('(SELECT COUNT(DISTINCT s2.step_id) FROM lms_step_user s2
                                    WHERE s2.user_id = lms_step_user.user_id
                                    AND s2.completed_at IS NOT NULL
                                    AND s2.step_id IN (
                                        SELECT s3.id FROM lms_steps s3
                                        JOIN lms_lessons l3 ON s3.lesson_id = l3.id
                                        WHERE l3.course_id = lms_courses.id
                                    )) < (
                                    SELECT COUNT(DISTINCT s4.id)
                                    FROM lms_steps s4
                                    JOIN lms_lessons l4 ON s4.lesson_id = l4.id
                                    WHERE l4.course_id = lms_courses.id
                                )');
                            }
                        });
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
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () use ($table) {
                        $query = $table->getQuery();

                        // Apply all active filters
                        foreach ($table->getFilters() as $filter) {
                            $state = $filter->getState();
                            if (! empty($state)) {
                                $filter->apply($query, $state);
                            }
                        }

                        return Excel::download(
                            new CourseProgressExport($query),
                            'course-progress-'.now()->format('Y-m-d').'.xlsx'
                        );
                    }),
            ])
            ->defaultSort(function (Builder $query) {
                // Use raw SQL for ordering to avoid ONLY_FULL_GROUP_BY issues
                return $query->orderByRaw('MAX(lms_step_user.completed_at) DESC');
            });
    }
}
