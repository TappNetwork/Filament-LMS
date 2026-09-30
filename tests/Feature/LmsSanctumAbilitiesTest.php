<?php

declare(strict_types=1);

use Tapp\FilamentLms\FilamentLmsServiceProvider;
use Tapp\FilamentLms\Mcp\LmsServer;

beforeEach(function () {
    if (! class_exists(LmsServer::class)) {
        $this->markTestSkipped('laravel/mcp is required for Sanctum ability tests.');
    }
});

test('sanctumAbilities returns every mcp tool name and title', function () {
    $abilities = LmsServer::sanctumAbilities();

    expect($abilities)->toHaveCount(11)
        ->and($abilities)->toHaveKey('list_courses')
        ->and($abilities)->toHaveKey('delete_course')
        ->and($abilities['list_courses'])->toBe('List Courses');
});

test('ability list is only lms tools when filament-sanctum is unpublished', function () {
    $published = config_path('filament-sanctum.php');

    if (file_exists($published)) {
        unlink($published);
    }

    config([
        'filament-lms.mcp.contribute_abilities' => true,
        'filament-sanctum.abilities.list' => [
            'users:read' => 'Read User',
            'blog:create' => 'Create Blog',
        ],
    ]);

    contributeSanctumAbilities();

    expect(config('filament-sanctum.abilities.list'))
        ->toBe(LmsServer::sanctumAbilities())
        ->not->toHaveKey('users:read')
        ->not->toHaveKey('blog:create');
});

test('published filament-sanctum list merges after lms tools and wins on shared keys', function () {
    $published = config_path('filament-sanctum.php');

    file_put_contents($published, <<<'PHP'
<?php

return [
    'abilities' => [
        'list' => [
            'list_courses' => 'App List Label',
            'reports:read' => 'Read reports',
        ],
    ],
];
PHP);

    config([
        'filament-lms.mcp.contribute_abilities' => true,
        'filament-sanctum.abilities.list' => [
            'list_courses' => 'App List Label',
            'reports:read' => 'Read reports',
        ],
    ]);

    try {
        contributeSanctumAbilities();

        $list = config('filament-sanctum.abilities.list');

        expect($list['list_courses'])->toBe('App List Label')
            ->and($list)->toHaveKey('reports:read')
            ->and($list['reports:read'])->toBe('Read reports')
            ->and($list)->toHaveKey('delete_course')
            ->and($list)->toHaveKey('create_video_course');
    } finally {
        if (file_exists($published)) {
            unlink($published);
        }
    }
});

test('contribute_abilities false leaves the ability list untouched', function () {
    config([
        'filament-lms.mcp.contribute_abilities' => false,
        'filament-sanctum.abilities.list' => [
            'custom:ability' => 'Custom',
        ],
    ]);

    contributeSanctumAbilities();

    expect(config('filament-sanctum.abilities.list'))->toBe([
        'custom:ability' => 'Custom',
    ]);
});

function contributeSanctumAbilities(): void
{
    $provider = app()->getProvider(FilamentLmsServiceProvider::class);

    expect($provider)->not->toBeNull();

    $method = new ReflectionMethod($provider, 'contributeSanctumAbilities');
    $method->invoke($provider);
}
