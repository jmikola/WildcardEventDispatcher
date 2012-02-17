# WildcardEventDispatcher

This library implements an event dispatcher, based on [Symfony2's interface][],
with wildcard syntax inspired by AMQP topic exchanges. Listeners may be bound to
a wildcard pattern and be notified if a dispatched event's name matches that
pattern. Literal event name matching is still supported.

If you are interested in using this library in a Symfony2 project, you may also
want to take a look at the corresponding [bundle][].

  [Symfony2's interface]: https://github.com/symfony/EventDispatcher
  [bundle]: https://github.com/jmikola/JmikolaWildcardEventDispatcherBundle

## Usage ##

WildcardEventDispatcher implements EventDispatcherInterface and may be used as
you would Symfony2's standard EventDispatcher:

```php
<?php

use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Symfony\Component\EventDispatcher\Event;

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('core.*', function(Event $e) {
    echo $e->getName();
});
$dispatcher->dispatch('core.request');

// "core.request" will be printed
```

Internally, WildcardEventDispatcher actually [composes][] an
EventDispatcherInterface instance, which it relies upon for event handling. By
default, WildcardEventDispatcher will construct an EventDispatcher object for
internal use, but you may specify a particular EventDispatcherInterface instance
to wrap in the constructor:

```php
<?php

use Jmikola\WildcardEventDispatcher\WildcardEventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;

$dispatcher = new WildcardEventDispatcher(new EventDispatcher());
```

  [composes]: http://en.wikipedia.org/wiki/Object_composition

## Wildcard Syntax ##

### Single-word Wildcard ###

Consider the scenario where the same listener is defined for multiple events,
all of which share a common prefix:

```php
<?php

$coreListener = function(Event $e) {};

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('core.exception', $coreListener);
$dispatcher->addListener('core.request', $coreListener);
$dispatcher->addListener('core.response', $coreListener);
```

These event names all consist of two dot-separated words. This concept of a word
will be important in understanding how wildcard patterns apply.

In this example, the listener is responsible for observing all `core` events in
the application. Let's suppose it needs to log some details about these events
to an external server. We can refactor multiple `addListener()` calls by
using the single-word `*` wildcard:

```php
<?php

$coreListener = function(Event $e) {};

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('core.*', $coreListener);
```

The listener will now observe all events named `core` or starting with `core.`
and followed by another word. The matching of `core` alone may not make sense,
but this is implemented in order to be consistent with AMQP. A trailing `*`
after a non-empty sequence may match the preceding sequence sans `.*`.

### Multi-word Wildcard ###

Suppose there was a `core` event in your application named `core.foo.bar`. The
aforementioned `core.*` pattern would not catch this event. You could use:

```php
<?php

$coreListener = function(Event $e) {};

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('core.*.*', $coreListener);
```

This syntax would match `core.foo` and `core.foo.bar`, but `core` would no
longer be matched (assuming there was such an event).

The multi-word `#` wildcard might be more appropriate here:

```php
<?php

$coreListener = function(Event $e) {};

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('core.#', $coreListener);
```

Suppose there was also an listener in the application that needed to listen on
_all_ events in the application. The multi-word `#` wildcard could be used:

```php
<?php

$allListener = function(Event $e) {};

$dispatcher = new WildcardEventDispatcher();
$dispatcher->addListener('#', $allListener);
```

### Additional Wildcard Documentation ###

When in doubt, the unit tests for `ListenerPattern` are a good resource for
inferring how wildcards will be interpreted. This library aims to mimic the
behavior of AMQP topic wildcards completely, but there may be shortcomings.

Documentation for actual AMQP syntax may be found in the following packages:

 * [ActiveMQ](http://activemq.apache.org/wildcards.html)
 * [HornetQ](http://docs.jboss.org/hornetq/2.2.5.Final/user-manual/en/html/wildcard-syntax.html)
 * [RabbitMQ](http://www.rabbitmq.com/faq.html#wildcards-in-topic-exchanges)
 * [ZeroMQ](http://www.zeromq.org/whitepapers:message-matching)
