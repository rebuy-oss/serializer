<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Performance;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class UnrelatedEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'unrelated.event' => 'onEvent',
        ];
    }

    public function onEvent(object $event): void
    {
    }
}
