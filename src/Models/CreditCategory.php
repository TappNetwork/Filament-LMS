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
 * @property string $color
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, CourseCreditCategory> $courseCreditCategories
 */
final class CreditCategory extends Model
{
    use HasFactory;

    /** @var array<string, string> */
    public const COLORS = [
        'red' => '#ef4444',
        'orange' => '#f97316',
        'amber' => '#f59e0b',
        'lime' => '#84cc16',
        'green' => '#22c55e',
        'emerald' => '#10b981',
        'teal' => '#14b8a6',
        'cyan' => '#06b6d4',
        'sky' => '#0ea5e9',
        'blue' => '#3b82f6',
        'indigo' => '#6366f1',
        'violet' => '#8b5cf6',
        'purple' => '#a855f7',
        'fuchsia' => '#d946ef',
        'pink' => '#ec4899',
        'rose' => '#f43f5e',
    ];

    protected $guarded = [];

    protected $table = 'lms_credit_categories';

    public function courseCreditCategories(): HasMany
    {
        return $this->hasMany(CourseCreditCategory::class);
    }

    public function hexColor(): string
    {
        return self::COLORS[$this->color] ?? '#6b7280';
    }

    public function badgeStyle(): string
    {
        $hex = $this->hexColor();

        return "background-color: {$hex}1a; color: {$hex};";
    }

    public static function nextAvailableColor(): string
    {
        $usedColors = self::query()->pluck('color')->toArray();

        foreach (array_keys(self::COLORS) as $color) {
            if (! in_array($color, $usedColors)) {
                return $color;
            }
        }

        return array_key_first(self::COLORS);
    }
}
