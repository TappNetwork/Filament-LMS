<?php

namespace Tapp\FilamentLms\Models;

use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Tapp\FilamentFormBuilder\Models\FilamentForm;
use Tapp\FilamentFormBuilder\Models\FilamentFormUser;
use Tapp\FilamentLms\Contracts\FilamentLmsUserInterface;
use Tapp\FilamentLms\Database\Factories\StepFactory;
use Tapp\FilamentLms\Events\CourseStarted;
use Tapp\FilamentLms\Events\StepCompleted;
use Tapp\FilamentLms\Models\Traits\BelongsToTenant;
use Tapp\FilamentLms\Pages\Step as StepPage;

/**
 * @property int $id
 * @property int $lesson_id
 * @property int $order
 * @property bool $is_optional
 * @property string $name
 * @property string $slug
 * @property string $type
 * @property string|null $text
 * @property int|null $material_id
 * @property string|null $material_type
 * @property string|null $player_slide_id
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property int|null $retry_step_id
 * @property bool $require_perfect_score
 * @property-read Lesson $lesson
 * @property-read Model|null $material
 * @property-read StepUser|null $progress
 * @property-read Step|null $retryStep
 */
class Step extends Model implements Sortable
{
    use BelongsToTenant;
    use HasFactory;
    use SortableTrait;

    public $sortable = [
        'order_column_name' => 'order',
    ];

    protected $guarded = [];

    protected $table = 'lms_steps';

    protected $casts = [
        'is_optional' => 'boolean',
        'require_perfect_score' => 'boolean',
    ];

    private const ALLOWED_RENDERED_TEXT_TAGS = [
        'a',
        'blockquote',
        'br',
        'code',
        'em',
        'h1',
        'h2',
        'h3',
        'h4',
        'h5',
        'h6',
        'li',
        'ol',
        'p',
        'pre',
        'strong',
        'ul',
    ];

    private const ALLOWED_RENDERED_TEXT_ATTRIBUTES = [
        'a' => ['href', 'rel', 'target', 'title'],
    ];

    private const DANGEROUS_RENDERED_TEXT_TAGS = [
        'embed',
        'iframe',
        'math',
        'object',
        'script',
        'style',
        'svg',
    ];

    protected static function newFactory()
    {
        return StepFactory::new();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function material(): MorphTo
    {
        return $this->morphTo();
    }

    public function retryStep(): BelongsTo
    {
        return $this->belongsTo(Step::class, 'retry_step_id');
    }

    public function getRenderedText(): string
    {
        $text = mb_trim((string) $this->text);
        if ($text === '') {
            return '';
        }

        $html = str_starts_with(ltrim($text), '<')
            ? $text
            : Str::markdown($text);

        return $this->sanitizeRenderedText($html);
    }

    private function sanitizeRenderedText(string $html): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><div id="lms-rendered-text-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('lms-rendered-text-root');
        if (! $root instanceof DOMElement) {
            return '';
        }

        $this->sanitizeRenderedTextNode($root);

        $rendered = '';
        foreach ($root->childNodes as $childNode) {
            $rendered .= $document->saveHTML($childNode) ?: '';
        }

        return $rendered;
    }

    private function sanitizeRenderedTextNode(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;

            if (! $child instanceof DOMElement) {
                continue;
            }

            $tagName = mb_strtolower($child->tagName);
            if (! in_array($tagName, self::ALLOWED_RENDERED_TEXT_TAGS, true)) {
                if (! in_array($tagName, self::DANGEROUS_RENDERED_TEXT_TAGS, true)) {
                    $this->sanitizeRenderedTextNode($child);
                    while ($child->firstChild !== null) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                }

                $child->parentNode?->removeChild($child);

                continue;
            }

            $this->sanitizeRenderedTextAttributes($child, $tagName);
            $this->sanitizeRenderedTextNode($child);
        }
    }

    private function sanitizeRenderedTextAttributes(DOMElement $element, string $tagName): void
    {
        $allowedAttributes = self::ALLOWED_RENDERED_TEXT_ATTRIBUTES[$tagName] ?? [];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            if (! in_array($attribute->name, $allowedAttributes, true)) {
                $element->removeAttribute($attribute->name);
            }
        }

        if ($tagName !== 'a') {
            return;
        }

        $href = html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href !== '' && preg_match('/^\s*(javascript|data|vbscript):/i', $href) === 1) {
            $element->removeAttribute('href');
        }

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    public function complete($user = null, ?int $evaluationPrimaryCourseId = null)
    {
        // @phpstan-ignore-next-line
        $user = $user ?: Auth::user();

        $userStepQuery = StepUser::where('user_id', $user->id)
            ->where('step_id', $this->id)
            ->when(
                $evaluationPrimaryCourseId !== null,
                fn ($query) => $query->where('evaluation_primary_course_id', $evaluationPrimaryCourseId),
                fn ($query) => $query->whereNull('evaluation_primary_course_id'),
            );

        $userStep = $userStepQuery->first();

        $nextStep = $this->next_step;

        if (! $userStep) {
            // update progress
            StepUser::create([
                'user_id' => $user->id,
                'step_id' => $this->id,
                'evaluation_primary_course_id' => $evaluationPrimaryCourseId,
                'completed_at' => now(),
            ]);

            if ($this->first_step) {
                CourseStarted::dispatch($user, $this->lesson->course);
            }

            StepCompleted::dispatch($user, $this);
        } elseif (! $userStep->completed_at) {
            // a step may have been started but not completed (e.g. video paused)
            $userStep->update([
                'completed_at' => now(),
            ]);

            StepCompleted::dispatch($user, $this);
        } else {
            // step is already completed
        }

        if ($nextStep) {
            StepUser::firstOrCreate([
                'user_id' => $user->id,
                'step_id' => $nextStep->id,
                'evaluation_primary_course_id' => $evaluationPrimaryCourseId,
            ]);

            $this->lesson->course->maybeSetCompletedAtForUser($user->id);

            return $nextStep;
        } else {
            $this->lesson->course->maybeSetCompletedAtForUser($user->id);
        }
    }

    public function getNextStepAttribute()
    {
        $nextInLesson = $this->lesson->steps()->where('order', '>', $this->order)->first();

        if ($nextInLesson) {
            return $nextInLesson;
        }

        $nextLesson = $this->lesson->course->lessons()->where('order', '>', $this->lesson->order)->first();

        return $nextLesson ? $nextLesson->steps()->first() : null;
    }

    /**
     * returns true if last step in last lesson of the course
     */
    public function getLastStepAttribute()
    {
        return $this->lesson->steps->last()->is($this) && $this->lesson->course->lessons->last()->is($this->lesson);
    }

    /**
     * returns true if last step in last lesson of the course
     */
    public function getFirstStepAttribute()
    {
        return $this->lesson->steps->first()->is($this) && $this->lesson->course->lessons->first()->is($this->lesson);
    }

    public function isActive()
    {
        $route = request()->route();

        if (! $route) {
            return false;
        }

        $courseSlug = $route->parameter('courseSlug');
        $lessonSlug = $route->parameter('lessonSlug');
        $stepSlug = $route->parameter('stepSlug');

        // Only check if we're on a step page (has these parameters)
        if ($courseSlug === null || $lessonSlug === null || $stepSlug === null) {
            return false;
        }

        return $courseSlug === $this->lesson->course->slug
            && $lessonSlug === $this->lesson->slug
            && $stepSlug === $this->slug;
    }

    public function getUrlAttribute()
    {
        return StepPage::getUrlForStep($this);
    }

    public function videoProgress(int $seconds): void
    {
        // @phpstan-ignore-next-line
        $user = Auth::user();

        $userStep = StepUser::where('user_id', $user->id)
            ->where('step_id', $this->id)
            ->first();

        if (! $userStep) {
            StepUser::create([
                'user_id' => $user->id,
                'step_id' => $this->id,
                'seconds' => $seconds,
            ]);

            if ($this->first_step) {
                CourseStarted::dispatch($user, $this->lesson->course);
            }
        } else {
            $userStep->update([
                'seconds' => $seconds,
            ]);
        }
    }

    /**
     * The progress for current user
     */
    public function progress(): HasOne
    {
        // @phpstan-ignore-next-line
        $currentUserId = Auth::check() ? Auth::user()->id : null;

        return $this->hasOne(StepUser::class)->ofMany([
            // TODO is this started_at => max needed?
            'created_at' => 'max',
        ], function ($query) use ($currentUserId) {
            $query->where('user_id', '=', $currentUserId);
        });
    }

    public function getCompletedAtAttribute()
    {
        return $this->progress?->completed_at;
    }

    public function getAvailableAttribute()
    {
        // @phpstan-ignore-next-line
        if (! Auth::check()) {
            return false;
        }

        // @phpstan-ignore-next-line
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        // Type assertion for PHPStan: user implements FilamentLmsUserInterface
        /** @var FilamentLmsUserInterface $user */
        // Use canAccessStep (respects user overrides like admin access)
        // No circular dependency: trait's canAccessStep calls checkPreviousStepsCompleted directly
        return $user->canAccessStep($this);
    }

    public function checkPreviousStepsCompleted(): bool
    {
        if ($this->completed_at) {
            return true;
        }

        $previousSteps = $this->lesson->course->steps()
            ->where(function ($query) {
                $query->where('lms_lessons.order', '<', $this->lesson->order)
                    ->orWhere(function ($query) {
                        $query->where('lms_lessons.order', '=', $this->lesson->order)
                            ->where('lms_steps.order', '<', $this->order);
                    });
            })
            ->with('progress')
            ->get();

        return $previousSteps->every(fn ($step) => $step->completed_at !== null);
    }

    public function getSecondsAttribute()
    {
        return $this->progress?->seconds ?? 0;
    }

    public function formEntryForUser(int|string $userId, ?int $evaluationPrimaryCourseId = null): ?FilamentFormUser
    {
        if ($this->material_type !== 'form') {
            return null;
        }

        $this->loadMissing('material');

        if (! $this->material instanceof FilamentForm) {
            return null;
        }

        $stepUser = StepUser::query()
            ->where('user_id', $userId)
            ->where('step_id', $this->id)
            ->when(
                $evaluationPrimaryCourseId !== null,
                fn ($query) => $query->where('evaluation_primary_course_id', $evaluationPrimaryCourseId),
                fn ($query) => $query->whereNull('evaluation_primary_course_id'),
            )
            ->whereNotNull('completed_at')
            ->first();

        if ($stepUser === null) {
            return null;
        }

        if ($stepUser->filament_form_user_id !== null) {
            return FilamentFormUser::query()
                ->whereKey($stepUser->filament_form_user_id)
                ->where('user_id', $userId)
                ->where('filament_form_id', $this->material->id)
                ->first();
        }

        return FilamentFormUser::query()
            ->where('filament_form_id', $this->material->id)
            ->where('user_id', $userId)
            ->where('created_at', '<=', $stepUser->completed_at)
            ->orderByDesc('created_at')
            ->first();
    }
}
