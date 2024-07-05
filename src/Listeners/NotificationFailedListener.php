<?php

namespace Hazuli\LaravelOtel\Listeners;

use Hazuli\LaravelOtel\Facades\Span;
use Hazuli\LaravelOtel\Support\SpanBuilder;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Str;

class NotificationFailedListener
{
    public function handle(NotificationFailed $event){
        $end = now();

        $notifiable = $event->notifiable;
        $notification = $event->notification;
        $notificationStart = $event->data['start'];

        Span::start((new SpanBuilder())->failedNotification(get_class($notification)), (int) $notificationStart->getPreciseTimestamp() * 1_000);

        $properties = [];
        foreach(get_object_vars($notification) as $propertyName=>$propertyValue) {
            if (is_object($propertyValue) || is_array($propertyValue)) {
                $propertyValue = json_encode($propertyValue);
            }
            $properties['notification.'.$propertyName] = $propertyValue;
        }

        Span::addEvent('record data', array_merge($properties, [
            'notifiable' => json_encode($notifiable),
            'channel' => get_class($event->channel),
        ]));

        Span::exception($event->data['exception']);

        Span::error()->stop((int) $end->getPreciseTimestamp() * 1_000)->detach();
    }

    private function extractDbOperation(string $sql): ?string
    {
        if (Str::startsWith(Str::upper($sql), 'SELECT')) {
            return 'SELECT';
        }

        if (Str::startsWith(Str::upper($sql), 'INSERT')) {
            return 'INSERT';
        }

        if (Str::startsWith(Str::upper($sql), 'UPDATE')) {
            return 'UPDATE';
        }

        if (Str::startsWith(Str::upper($sql), 'DELETE')) {
            return 'DELETE';
        }

        return null;
    }
}