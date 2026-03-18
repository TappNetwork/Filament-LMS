<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CourseCreditCategory> $courseCreditCategories
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
