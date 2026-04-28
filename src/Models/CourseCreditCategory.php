<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tapp\FilamentLms\Database\Factories\CourseCreditCategoryFactory;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;

/**
 * @property int $id
 * @property int $course_id
 * @property int $credit_category_id
 * @property float $credits
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Course $course
 * @property-read CreditCategory $creditCategory
 */
class CourseCreditCategory extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = [];

    protected $table = 'lms_course_credit_category';

    protected static function newFactory(): CourseCreditCategoryFactory
    {
        return CourseCreditCategoryFactory::new();
    }

    protected function casts(): array
    {
        return [
            'credits' => 'decimal:2',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creditCategory(): BelongsTo
    {
        return $this->belongsTo(CreditCategory::class);
    }
}
