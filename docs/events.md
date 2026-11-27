# Events

Similar to JMS Serializer, the rebuy Serializer can dispatch events before and
after (de-)serialization. Pass any implementation of the
`symfony/event-dispatcher-contracts` `EventDispatcherInterface` to
`withEventDispatcher()`. Without a dispatcher, no events are dispatched.

Unlike JMS, the events are only dispatched for the root object, not for nested
objects.

| Event                  | What a listener can change                       |
|------------------------|--------------------------------------------------|
| `PreSerializeEvent`    | Replace the object before it is serialized       |
| `PostSerializeEvent`   | Change the serialized array before JSON encoding |
| `PreDeserializeEvent`  | Change the input array and the target type       |
| `PostDeserializeEvent` | Replace the deserialized object                  |

All events extend `SerializerEvent` and give access to the `Context` and the
format. The format is `null` for `toArray` and `fromArray`.

In this example, we add a field to every serialized `Product`:

```php
use Acme\Model\Product;
use Liip\Serializer\Configuration\SerializerConfigBuilder;
use Liip\Serializer\Event\PostSerializeEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new EventDispatcher();
$dispatcher->addListener(PostSerializeEvent::class, static function (PostSerializeEvent $event): void {
    if (!$event->getObject() instanceof Product) {
        return;
    }

    $event->setData([...$event->getData(), 'type' => 'product']);
});

$serializer = SerializerConfigBuilder::create($cacheDirectory)
    ->withEventDispatcher($dispatcher)
    ->buildSerializer();
```
