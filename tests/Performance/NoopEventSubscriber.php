<?php

declare(strict_types=1);

namespace Tests\Liip\Serializer\Performance;

use Liip\Serializer\Event\PostDeserializeEvent;
use Liip\Serializer\Event\PostSerializeEvent;
use Liip\Serializer\Event\PreDeserializeEvent;
use Liip\Serializer\Event\PreSerializeEvent;
use Liip\Serializer\Event\SerializerEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class NoopEventSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PreSerializeEvent::class => 'onEvent',
            PostSerializeEvent::class => 'onEvent',
            PreDeserializeEvent::class => 'onEvent',
            PostDeserializeEvent::class => 'onEvent',
        ];
    }

    public function onEvent(SerializerEvent $event): void
    {
    }
}
