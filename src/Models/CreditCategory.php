<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, CourseCreditCategory> $courseCreditCategories
 */
final class CreditCategory extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_credit_categories';

    public function courseCreditCategories(): HasMany
    {
        return $this->hasMany(CourseCreditCategory::class);
    }
}
