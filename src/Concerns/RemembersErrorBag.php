<?php

declare(strict_types=1);

namespace Tapp\FilamentLms\Concerns;

use Illuminate\Support\MessageBag;

/**
 * Keeps the Livewire error bag on the component when the shared store does not.
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
