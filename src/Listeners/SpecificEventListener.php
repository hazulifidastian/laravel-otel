<?php

namespace Hazuli\LaravelOtel\Listeners;

use Hazuli\LaravelOtel\Facades\Span;

class SpecificEventListener
{
    public function __invoke($event) {
        Span::addEvent(sprintf('Event %s fired', get_class($event)), [
            'event.name' => $event,
        ]);
    }
}