<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Concerns;

use Illuminate\Support\MessageBag;

/**
 * Keeps the Livewire error bag on the component when the shared store does not.
 *
 * The integrated course editor renders EditCourse/CreateCourse alongside
 * several RelationManagers (Lessons, Users, User Groups) and, in builder
 * mode, a nested Livewire child (CourseCurriculum) inside a Tabs schema.
 * Livewire's default getErrorBag()/setErrorBag() read and write a MessageBag
 * keyed into the shared `errors` ViewErrorBag. When more than one of these
 * Livewire components is mounted for the same page — which is the normal
 * case here, not an edge case — a later component's SupportValidation calls
 * ViewErrorBag::put() with a null bag because the shared store does not
 * reliably keep a bag for every component across mount/render cycles,
 * crashing with "Argument #2 ($bag) must be of type MessageBag, null given".
 *
 * This was confirmed by removing the trait from each of the affected
 * classes (CourseCurriculum, CreateCourse, EditCourse,
 * LessonsRelationManager, CourseUsersRelationManager,
 * CourseUserGroupsRelationManager) one at a time: every one of them is
 * independently required, each reproducing the crash in the feature test
 * suite for both legacy (RelationManager tabs) and builder (nested
 * Livewire + Access tab) course editor modes. It is not masking an
 * application-level bug — it is working around Livewire not keeping a
 * durable error bag per component when several Livewire components share
 * one page.
 */
trait RemembersErrorBag
{
    protected ?MessageBag $rememberedErrorBag = null;

    public function getErrorBag(): MessageBag
    {
        if ($this->rememberedErrorBag instanceof MessageBag) {
            return $this->rememberedErrorBag;
        }

        $bag = parent::getErrorBag();

        if ($bag instanceof MessageBag) {
            return $this->rememberedErrorBag = $bag;
        }

        return $this->rememberedErrorBag = new MessageBag;
    }

    public function setErrorBag($bag): MessageBag
    {
        $this->rememberedErrorBag = $bag instanceof MessageBag
            ? $bag
            : new MessageBag(is_array($bag) ? $bag : []);

        parent::setErrorBag($this->rememberedErrorBag);

        return $this->rememberedErrorBag;
    }
}
