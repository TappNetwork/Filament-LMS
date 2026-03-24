<?php

namespace Tapp\FilamentLms\Pages;

use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\Models\CreditCategory;

class Dashboard extends \Filament\Pages\Dashboard
{
    use HasFiltersForm;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament-lms::pages.dashboard';

    protected static string $routePath = '/';

    protected static ?string $title = 'Courses';

    public $courses;

    public function mount(): void
    {
        $user = Auth::user();

        $creditEager = config('filament-lms.credits_enabled')
            ? ['courseCreditCategories.creditCategory']
            : [];

        if ($user) {
            $courses = Course::accessibleTo($user)
                ->with(array_merge($creditEager, ['authEnrollment']))
                ->withCount('lessons')
                ->get();
        } else {
            $courses = Course::where('is_private', false)
                ->whereHas('steps')
                ->with($creditEager)
                ->withCount('lessons')
                ->get();
        }

        $this->courses = $courses;
    }

    public function filtersForm(Schema $schema): Schema
    {
        if (! config('filament-lms.credits_enabled', false)) {
            return $schema;
        }

        return $schema->components([
            Select::make('credit_category_id')
                ->label('Credit Category')
                ->options(fn (): array => CreditCategory::query()->orderBy('name')->pluck('name', 'id')->all())
                ->placeholder('All categories')
                ->native(false),
        ]);
    }

    #[Computed]
    public function filteredCourses(): Collection
    {
        $courses = $this->courses ?? collect();

        $creditCategoryId = $this->filters['credit_category_id'] ?? null;
        if (blank($creditCategoryId)) {
            return $courses;
        }

        return $courses->filter(function (Course $course) use ($creditCategoryId): bool {
            return $course->courseCreditCategories->contains('credit_category_id', (int) $creditCategoryId);
        })->values();
    }

    public function hasActiveFilters(): bool
    {
        if (! config('filament-lms.credits_enabled', false)) {
            return false;
        }

        $creditCategoryId = $this->filters['credit_category_id'] ?? null;

        return ! blank($creditCategoryId);
    }
}
