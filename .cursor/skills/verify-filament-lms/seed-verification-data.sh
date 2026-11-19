#!/usr/bin/env bash
# Seed verification data for Filament LMS
set -euo pipefail

readonly DB_FILE="vendor/orchestra/testbench-core/laravel/database/database.sqlite"

echo "🔧 Setting up Filament LMS..."

# Create persistent database
rm -f "$DB_FILE"
touch "$DB_FILE"
echo "✓ Created database file"

# Run migrations
echo "Running migrations..."
DB_DATABASE="$DB_FILE" php vendor/bin/testbench migrate --force --path=database/migrations >/dev/null 2>&1
DB_DATABASE="$DB_FILE" php vendor/bin/testbench migrate --force --path=vendor/tapp/filament-form-builder/database/migrations >/dev/null 2>&1 || true
DB_DATABASE="$DB_FILE" php vendor/bin/testbench migrate --force --path=vendor/tapp/filament-certificate-builder/database/migrations >/dev/null 2>&1 || true
echo "✓ Migrations completed"

# Run a simple Pest test to seed data
echo "Creating test data..."
DB_DATABASE="$DB_FILE" php vendor/bin/testbench tinker --execute='
$user = \Tapp\FilamentLms\Tests\TestUser::create([
    "name" => "Test Admin",
    "email" => "admin@example.com",
    "password" => bcrypt("password"),
]);

$course = \Tapp\FilamentLms\Models\Course::factory()->create([
    "name" => "Introduction to Filament LMS",
    "slug" => "intro-to-filament-lms",
    "external_id" => "intro_lms",
    "is_private" => false,
]);

$lesson1 = \Tapp\FilamentLms\Models\Lesson::factory()->create([
    "course_id" => $course->id,
    "name" => "Getting Started",
    "slug" => "getting-started",
    "order" => 1,
]);

$lesson2 = \Tapp\FilamentLms\Models\Lesson::factory()->create([
    "course_id" => $course->id,
    "name" => "Advanced Features", 
    "slug" => "advanced-features",
    "order" => 2,
]);

$video1 = \Tapp\FilamentLms\Models\Video::factory()->create();
$video2 = \Tapp\FilamentLms\Models\Video::factory()->create();
$video3 = \Tapp\FilamentLms\Models\Video::factory()->create();

\Tapp\FilamentLms\Models\Step::factory()->create([
    "lesson_id" => $lesson1->id,
    "name" => "Welcome",
    "slug" => "getting-started-welcome",
    "material_type" => get_class($video1),
    "material_id" => $video1->id,
    "order" => 1,
]);

\Tapp\FilamentLms\Models\Step::factory()->create([
    "lesson_id" => $lesson1->id,
    "name" => "Installation",
    "slug" => "getting-started-installation",
    "material_type" => get_class($video2),
    "material_id" => $video2->id,
    "order" => 2,
]);

\Tapp\FilamentLms\Models\Step::factory()->create([
    "lesson_id" => $lesson2->id,
    "name" => "Admin Panel",
    "slug" => "advanced-features-admin-panel",
    "material_type" => get_class($video3),
    "material_id" => $video3->id,
    "order" => 1,
]);

echo "✓ Test data created\n";
' 2>&1 | tail -5

echo ""
echo "✅ Setup complete!"
echo ""
echo "Login: admin@example.com / password"
echo ""
echo "Start server:"
echo "  DB_DATABASE=$DB_FILE php vendor/bin/testbench serve --host=0.0.0.0 --port=8000"
