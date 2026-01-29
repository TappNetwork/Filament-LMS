<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Tapp\FilamentLms\Exports\CourseProgressExport;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Services\CourseProgressQueryService;

class Reporting extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected string $view = 'filament-lms::pages.reporting';

    protected static ?string $title = 'Reports';

    protected ?string $heading = 'Course Completion Reports';

    protected ?string $subheading = 'Track which users have completed courses and when they were completed.';

    protected static ?string $navigationLabel = 'Reporting';

    protected static ?string $slug = 'reporting';

    protected static string|\UnitEnum|null $navigationGroup = 'LMS';

    public static function canAccess(): bool
    {
        return Auth::check() && Gate::allows('viewLmsReporting');
    }

    public function getTableRecordKey(Model|array $record): string
    {
        if ($record instanceof Model) {
            $key = $record->getKey();
        }

        // For array records, create a unique composite key from user_id and course_id
        return $key ?? "user_{$record['user_id']}_course_{$record['course_id']}";
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(CourseProgressQueryService::buildQuery())
            ->columns([
                TextColumn::make('user_first_name')
                    ->label('First Name')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByFirstName($query, $direction);
                    }),

                TextColumn::make('user_last_name')
                    ->label('Last Name')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByLastName($query, $direction);
                    }),

                TextColumn::make('user_email')
                    ->label('User Email')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByEmail($query, $direction);
                    }),

                TextColumn::make('course_name')
                    ->label('Course')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByCourseName($query, $direction);
                    }),

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
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByStatus($query, $direction);
                    }),

                TextColumn::make('steps_completed')
                    ->label('Progress')
                    ->formatStateUsing(fn ($record) => "{$record['steps_completed']} / {$record['total_steps']}")
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByStepsCompleted($query, $direction);
                    }),

                TextColumn::make('started_at')
                    ->label('Date Started')
                    ->date()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByStartedAt($query, $direction);
                    }),

                TextColumn::make('completed_at')
                    ->label('Date Completed')
                    ->date()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return CourseProgressQueryService::sortByCompletedAt($query, $direction);
                    }),
            ])
            ->filters([
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('completed_from')
                            ->label('Completed From'),
                        DatePicker::make('completed_until')
                            ->label('Completed Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn (Builder $q, $date): Builder => $q->whereDate('lms_course_user.completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_until'],
                                fn (Builder $q, $date): Builder => $q->whereDate('lms_course_user.completed_at', '<=', $date),
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
                        return $query->when($data['value'], function ($q, $status) {
                            return $status === 'Completed'
                                ? $q->whereNotNull('lms_course_user.completed_at')
                                : $q->whereNull('lms_course_user.completed_at');
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
                Action::make('export')
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
            ]);
    }
}
