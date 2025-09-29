<?php

declare(strict_types=1);

namespace App\Infrastructure\Event;

/**
 * Minimal event dispatcher to register and dispatch events.
 */
class EventDispatcher
{
    /** @var array<string, callable[]> */
    private array $listeners = [];

    public function addListener(string $eventClass, callable $listener): void {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void {
        $class = $event::class;
        foreach ($this->listeners[$class] ?? [] as $listener) {
            $listener($event);
        }
    }
}
