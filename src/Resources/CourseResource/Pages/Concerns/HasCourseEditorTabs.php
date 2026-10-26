<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Resources\CourseResource\Pages\Concerns;

use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Tapp\FilamentLms\Livewire\CourseCurriculum;
use Tapp\FilamentLms\Models\Course;
use Tapp\FilamentLms\RelationManagers\CourseUserGroupsRelationManager;
use Tapp\FilamentLms\RelationManagers\CourseUsersRelationManager;
use Tapp\FilamentLms\Support\CourseEditor;

trait HasCourseEditorTabs
{
    public function content(Schema $schema): Schema
    {
        if (! CourseEditor::isBuilder()) {
            return parent::content($schema);
        }

        return $schema->components([
            Tabs::make(__('filament-lms::course-editor.tabs.label'))
                ->persistTabInQueryString()
                ->contained(false)
                ->tabs($this->courseEditorTabs()),
        ]);
    }

    /**
     * @return array<int, Tab>
     */
    protected function courseEditorTabs(): array
    {
        $record = $this->getRecord();
        $course = $record instanceof Course ? $record : null;

        return [
            Tab::make(__('filament-lms::course-editor.tabs.details'))
                ->schema([
                    $this->getFormContentComponent(),
                ]),
            Tab::make(__('filament-lms::course-editor.tabs.curriculum'))
                ->schema([
                    $course instanceof Course
                        ? Livewire::make(CourseCurriculum::class)
                            ->key('course-curriculum-'.$course->getKey())
                            ->data(['record' => $course])
                        : Text::make(__('filament-lms::course-editor.tabs.save_course_for_lessons')),
                ]),
            Tab::make(__('filament-lms::course-editor.tabs.access'))
                ->schema([
                    $course instanceof Course
                        ? View::make('filament-lms::filament.course-editor.access')
                            ->viewData([
                                'course' => $course,
                                'pageClass' => static::class,
                                'showUsers' => CourseUsersRelationManager::canViewForRecord($course, static::class),
                                'showGroups' => CourseUserGroupsRelationManager::canViewForRecord($course, static::class),
                            ])
                        : Text::make(__('filament-lms::course-editor.tabs.save_course_for_access')),
                ]),
        ];
    }
}
