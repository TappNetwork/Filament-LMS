<?php

namespace Tapp\FilamentLms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

test('scopes course unique indexes to the tenant column', function () {
    Schema::create('companies', function (Blueprint $table) {
        $table->id();
        $table->timestamps();
    });

    Schema::table('lms_courses', function (Blueprint $table) {
        $table->foreignId('company_id')->nullable()->constrained('companies');
    });

    config([
        'filament-lms.tenancy.enabled' => true,
        'filament-lms.tenancy.column' => 'company_id',
    ]);

    $migration = require dirname(__DIR__, 2).'/database/migrations/scope_lms_courses_unique_indexes_to_tenant.php.stub';

    $migration->up();
    $migration->up();

    expect(Schema::hasIndex('lms_courses', ['name'], 'unique'))->toBeFalse()
        ->and(Schema::hasIndex('lms_courses', ['slug'], 'unique'))->toBeFalse()
        ->and(Schema::hasIndex('lms_courses', ['external_id'], 'unique'))->toBeFalse()
        ->and(Schema::hasIndex('lms_courses', ['company_id', 'name'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('lms_courses', ['company_id', 'slug'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('lms_courses', ['company_id', 'external_id'], 'unique'))->toBeTrue();
});

test('does not change course unique indexes when tenancy is disabled', function () {
    $migration = require dirname(__DIR__, 2).'/database/migrations/scope_lms_courses_unique_indexes_to_tenant.php.stub';

    $migration->up();

    expect(Schema::hasIndex('lms_courses', ['name'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('lms_courses', ['slug'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('lms_courses', ['external_id'], 'unique'))->toBeTrue();
});
