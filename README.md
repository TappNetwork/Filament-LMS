# Filament LMS

An opinionated LMS plugin for Filament containing a user facing LMS panel and Resources for an existing admin panel

## Version Compatibility

 Filament | Filament LMS
:---------|:------------
 3.x      | 1.x
 4.x      | 4.x

## Installation

### Add the following to composer.json

``` json
"minimum-stability": "dev"
```

```json
"repositories": {
    "tapp/filament-lms": {
        "type": "vcs",
        "url": "https://github.com/tappnetwork/filament-lms"
    },
    "tapp/filament-form-builder": {
        "type": "vcs",
        "url": "https://github.com/tappnetwork/filament-form-builder"
    }
},
```

or

```json
{
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/tappnetwork/filament-lms"
        },
        {
            "type": "vcs",
            "url": "https://github.com/TappNetwork/Filament-Form-Builder"
        }
    ],
}
```

### For Filament 3

Please check the docs for [Filament 3 here](https://github.com/TappNetwork/Filament-LMS/tree/main)

### For Filament 4

```bash
composer require tapp/filament-lms:"^4.0"
```

### Publish

``` sh
php artisan vendor:publish --provider="Tapp\FilamentLms\FilamentLmsServiceProvider"
```

run migrations after publishing

### Add plugin to admin panel

This will create resources that allow admin to manage course material.

``` php
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->plugins([
                \Tapp\FilamentLms\Lms::make(),
            ])
    }
}
```

### Tailwind CSS Setup

This package uses Tailwind CSS classes in its Blade views. The configuration differs between Tailwind v3 and v4:

#### For Tailwind CSS v3

1. **Install Tailwind CSS** in your project (if not already installed):
```bash
npm install -D tailwindcss
npx tailwindcss init
```

2. **Configure Tailwind** to include the package's views in your `tailwind.config.js`:
```js
module.exports = {
  content: [
    // ... your existing content paths
    './vendor/tapp/filament-lms/resources/views/**/*.blade.php',
    './vendor/tapp/filament-form-builder/resources/views/**/*.blade.php',
  ],
  // ... rest of your config
}
```

3. **Include the package CSS** in your main CSS file (e.g., `resources/css/app.css`):
```css
@import 'tailwindcss/base';
@import 'tailwindcss/components';
@import 'tailwindcss/utilities';

/* Import the LMS package styles */
@import '../../vendor/tapp/filament-lms/dist/filament-lms.css';
```

4. **Build your CSS** to include both Tailwind and the package styles:
```bash
npm run build
```

#### For Tailwind CSS v4

1. **Install Tailwind CSS v4** in your project:
```bash
npm install -D @tailwindcss/vite@next
```

2. **Include the package CSS** in your main CSS file (e.g., `resources/css/app.css` or `resources/css/theme.css`):
```css
@import "tailwindcss";

/* Import the LMS package styles */
@import '../../vendor/tapp/filament-lms/dist/filament-lms.css';
```

3. **Build your CSS** to include both Tailwind and the package styles:
```bash
npm run build
```

**Note:** The package provides its own CSS for component-specific styling, while using Tailwind classes in views for layout and utilities. This approach ensures no dependency conflicts while maintaining the benefits of Tailwind CSS.

For more detailed Tailwind CSS configuration options, refer to the [official Tailwind CSS documentation](https://tailwindcss.com/docs).

# Development Reccomendations

- create the directory {project}/packages
- from within the packages directory, clone this repo
- (if necessary) add a type:path repository to project composer.json

# LMS Features

## Frontend LMS Panel
contains the LMS experience for the end user
### Course Library
- user can view courses available to them
- completion status
### Course UI
- shown when progressing through a single course
- left sidebar showing lessons with steps expanding from them
- icons in sidebar indicating the type of material for each step
- middleware to resume current step
- middleware to prevent skipping steps
### Other Frontend
- profile
- anything else?

## Admin Plugin
(should these be resource groups in existing panel or its own panel?)
### LMS resource group contains the following resources:
#### Course
- Top level of learning material data structure
- courses do not have an order. they are independant
- courses can be public or invite only
#### Lesson
- Intermediary level data structure
- Has Order (e.g. lesson 1 must be completed before starting lesson 2)
- *in the future* we may want to add support for lessons containing lessons to allow clients more customizability (lesson 1 contains lesson 1.1)
- name optional
#### Step
- Represents a single view in the LMS
- has order
- has material
- name optional
### Material Resource Group
- Video (do we use vimeo or something else?)
- Survey (form for student to fill out)
- Quiz (unlike a survey, a quiz has correct answers and a score)
- Text (Wysiwyg?)
- Image

## Configurations

This is the contents of the published config file:

```php
<?php

use Filament\Navigation\NavigationItem;

return [
    'theme' => 'default',
    'font' => 'Poppins',
    'home_url' => '/lms',
    'brand_name' => 'LMS',
    'brand_logo' => '',
    'brand_logo_height' => null,
    'vite_theme' => '',
    'colors' => [],
    'awards' => [
        'Default' => 'default',
    ],
    'top_navigation' => false,
    'show_exit_lms_link' => true,
];

```

### top_navigation

Set it to `true` to use top navigation instead of left sidebar on courses page.

### show_exit_lms_link

Use to display or not the `Exit LMS` link on top bar.

## Adding extra navigation items

To register new navigation items in the LMS panel, use the `boot()` method of your `AppPanelProvider.php` file:

```php
use Tapp\FilamentLms\LmsNavigation;
use Filament\Navigation\NavigationItem;

public function boot(): void
{
    LmsNavigation::addNavigation('lms',
        NavigationItem::make('Home')
            ->icon('heroicon-o-home')
            ->url(fn (): string => '/'),
    );
}
```

## Authorization

### Gates
The LMS package uses Laravel Gates for authorization. You'll need to define the following Gates in your application's `AuthServiceProvider`:

```php
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Gate::define('viewLmsReporting', function ($user) {
            // Customize this based on your application's needs
            return $user->hasRole('admin') || $user->hasPermission('view-lms-reporting');
        });
    }
}
```

#### Available Gates
- `viewLmsReporting`: Controls access to the LMS reporting page. Users must pass this Gate check to view the reporting interface.

## Course Authorization

### Restricting Course Visibility

To restrict users to only see courses they are assigned to, set the following in your `config/filament-lms.php`:

```php
'restrict_course_visibility' => true,
```

When enabled, users will only see courses assigned to them via the `lms_course_user` pivot table.

### User-Course Management in Filament

This package provides a reusable `CoursesRelationManager` and `AssignCoursesBulkAction` for managing user-course assignments. To enable course assignment in your User resource:

1. **Import the Relation Manager and Bulk Action:**

```php
use Tapp\FilamentLms\RelationManagers\CoursesRelationManager;
use Tapp\FilamentLms\Actions\AssignCoursesBulkAction;
```

2. **Register the CoursesRelationManager:**

```php
// In your UserResource.php
public static function getRelations(): array
{
    return [
        CoursesRelationManager::class,
        // ... other relation managers ...
    ];
}
```

3. **Add the AssignCoursesBulkAction to your bulk actions:**

```php
// In your UserResource.php
public static function table(Table $table): Table
{
    return $table
        // ...
        ->bulkActions([
            AssignCoursesBulkAction::make(),
            // ... other bulk actions ...
        ]);
}
```

## Overriding Course Visibility

If you need custom logic to determine whether a course is visible to a user, you can override the `isCourseVisibleForUser` method provided by the `FilamentLmsUser` trait in your User model. This method is used to filter which courses are shown to the user when course visibility restrictions are enabled.

If you want to call the trait's original method within your override, you can alias it when importing the trait:

```php
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class User extends Authenticatable
{
    use FilamentLmsUser {
        FilamentLmsUser::isCourseVisibleForUser as filamentLmsIsCourseVisibleForUser;
    }

    // ...

    public function isCourseVisibleForUser($course): bool
    {
        if ($this->hasAnyRole('admin', 'super_admin')) {
            return true;
        }
        // Call the trait's original method
        return $this->filamentLmsIsCourseVisibleForUser($course);
    }
}
```

This allows you to implement any business rules you need for course visibility, while still leveraging the default logic from the trait if desired.

## Step Access Control

### Customizing Step Access

The LMS package provides a flexible way to control which steps users can access through the `canAccessStep` method. This method is available on any model that uses the `FilamentLmsUser` trait and can be overridden to implement custom access control logic.

#### Default Behavior

By default, the `canAccessStep` method checks if the step is available based on the completion of previous steps in the course. This ensures users must complete steps in the proper sequence.

#### Overriding Step Access Control

To implement custom step access logic, override the `canAccessStep` method in your User model:

```php
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class User extends Authenticatable
{
    use FilamentLmsUser;

    // ...

    public function canAccessStep(Step $step): bool
    {
        // Allow admins to access all steps
        if ($this->hasRole('admin')) {
            return true;
        }

        // Fall back to default behavior (sequential step completion)
        return parent::canAccessStep($step);
    }
}
```

#### Where Step Access is Enforced

The `canAccessStep` method is automatically used in the following places:

1. **Step Page Access**: When users try to access a step directly via URL
2. **Navigation Links**: Determines which step links are clickable in the course navigation
3. **Current Step Detection**: Used when determining which step to redirect users to

#### Integration with Existing Logic

The `canAccessStep` method works alongside the existing `getAvailableAttribute()` method in the Step model. While `getAvailableAttribute()` handles the basic sequential completion logic, `canAccessStep` provides an additional layer of access control that can be customized per user.

### Customizing Step Edit Permissions

The LMS package also provides a way to control which users can edit steps through the `canEditStep` method. This method is separate from `canAccessStep` and specifically controls edit permissions.

#### Default Behavior

By default, the `canEditStep` method returns `false`, meaning no users have edit permissions. This ensures that step editing is disabled by default and must be explicitly enabled.

#### Overriding Step Edit Permissions

To implement custom step edit logic, override the `canEditStep` method in your User model:

```php
use Tapp\FilamentLms\Traits\FilamentLmsUser;

class User extends Authenticatable
{
    use FilamentLmsUser;

    // ...

    public function canEditStep(Step $step): bool
    {
        // Allow admins to edit all steps
        if ($this->hasRole('admin') || $this->hasRole('super_admin')) {
            return true;
        }

        // Allow course creators to edit their own courses
        if ($this->hasRole('instructor') && $step->lesson->course->created_by === $this->id) {
            return true;
        }

        // No edit permissions for other users
        return false;
    }
}
```

#### Where Step Edit Permissions are Used

The `canEditStep` method is used in the following places:

1. **Edit Button Visibility**: Controls whether the "Edit" button appears on step pages
2. **Admin Interface**: Can be used to control access to step editing in the Filament admin panel
3. **API Endpoints**: Can be used to secure step editing API endpoints

#### Separation of Concerns

Note that `canEditStep` is separate from `canAccessStep`:
- **`canAccessStep`**: Controls whether a user can view/access a step
- **`canEditStep`**: Controls whether a user can edit/modify a step

This separation allows for fine-grained control over user permissions, where users might be able to view steps but not edit them.
